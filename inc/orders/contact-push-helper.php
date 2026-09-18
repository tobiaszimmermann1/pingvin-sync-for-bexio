<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvContactPushHelper
 *
 * Ensures a WooCommerce order's customer exists in Bexio as a contact,
 * returning the Bexio contact ID. Called by PvOrderPushWorker before
 * creating a Bexio order document.
 *
 * Resolution order
 * ----------------
 * 1. WP user meta `_pvbexio_contact_id`   → verify + update billing if needed.
 *    A 404 on GET means the contact was deleted in Bexio: the stale meta is
 *    cleared and resolution falls through to search/create.
 * 2. POST 2.0/contact/search by email   → link + update billing if needed
 * 3. POST 2.0/contact                   → create in Bexio, link, return ID
 * 4. Returns null on any API failure — order push should be aborted (retried).
 *
 * Billing address handling
 * ------------------------
 * For existing contacts the WC billing address is compared against the
 * Bexio contact (GET 2.0/contact/{id}). If it differs, the contact is
 * updated via POST 2.0/contact/{id} with the new billing fields.
 * The delivery address is NOT stored on the contact — it is sent per-order
 * on the kb_order document (delivery_address_type=1 + delivery_address_manual).
 *
 * For guest orders (user_id = 0) the meta-save steps are skipped; the
 * Bexio contact ID is returned for use in the order payload only.
 */
class PvContactPushHelper {

  const LOG_PREFIX = '[ContactPush]';

  /**
   * Resolves (or creates) a Bexio contact for the given WC order.
   *
   * @param \WC_Order $order
   * @return int|null  Bexio contact ID, or null on failure.
   */
  public static function ensure_bexio_contact( \WC_Order $order ): ?int {
    $user_id       = (int) $order->get_user_id();
    $billing_email = trim( (string) $order->get_billing_email() );

    if ( $user_id > 0 ) {
      $cached = get_user_meta( $user_id, '_pvbexio_contact_id', true );
      if ( ! empty( $cached ) ) {
        $cached_id = (int) $cached;
        PingvinLogger::log(
          'info',
          self::LOG_PREFIX . " Using cached Bexio contact id={$cached_id} for WP user {$user_id} (order #{$order->get_id()})."
        );
        $sync = self::maybe_update_billing_address( $cached_id, $order );
        if ( $sync === 'ok' ) {
          $order->update_meta_data( '_pvbexio_contact_id', $cached_id );
          $order->save_meta_data();
          return $cached_id;
        }
        if ( $sync === 'not-found' ) {
          // Contact was deleted in Bexio — drop the stale link and fall
          // through to search-by-email / create below.
          delete_user_meta( $user_id, '_pvbexio_contact_id' );
          PingvinLogger::log(
            'warning',
            self::LOG_PREFIX . " Cached Bexio contact id={$cached_id} no longer exists — cleared stale link for WP user {$user_id}, re-resolving by email."
          );
        } else {
          return null; // Transient API error — abort so Action Scheduler retries.
        }
      }
    }

    if ( $billing_email === '' ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . ' Order #' . $order->get_id() . ' has no billing email — cannot resolve Bexio contact.'
      );
      return null;
    }

    $found_id = self::search_by_email( $billing_email );

    if ( $found_id === false ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " Contact search API error for {$billing_email} — aborting to avoid duplicate."
      );
      return null;
    }

    if ( $found_id !== null ) {
      $sync = self::maybe_update_billing_address( $found_id, $order );
      if ( $sync === 'not-found' ) {
        // Contact vanished between search and fetch — fall through to create.
        PingvinLogger::log(
          'warning',
          self::LOG_PREFIX . " Bexio contact id={$found_id} disappeared after search — creating a new contact for {$billing_email}."
        );
      } else {
        if ( $sync !== 'ok' ) {
          return null; // Transient API error — abort so Action Scheduler retries.
        }
        if ( $user_id > 0 ) {
          update_user_meta( $user_id, '_pvbexio_contact_id', $found_id );
        }
        $order->update_meta_data( '_pvbexio_contact_id', $found_id );
        $order->save_meta_data();
        PingvinLogger::log(
          'info',
          self::LOG_PREFIX . " Found existing Bexio contact id={$found_id} for email {$billing_email}."
        );
        return $found_id;
      }
    }

    $created_id = self::create_from_order( $order );

    if ( $created_id !== null ) {
      if ( $user_id > 0 ) {
        update_user_meta( $user_id, '_pvbexio_contact_id', $created_id );
      }
      $order->update_meta_data( '_pvbexio_contact_id', $created_id );
      $order->save_meta_data();
      PingvinLogger::log(
        'info',
        self::LOG_PREFIX . " Created Bexio contact id={$created_id} for email {$billing_email} (order #{$order->get_id()})."
      );
      return $created_id;
    }

    PingvinLogger::log(
      'error',
      self::LOG_PREFIX . ' Failed to resolve Bexio contact for order #'
      . $order->get_id() . " ({$billing_email})."
    );
    return null;
  }

  /**
   * @return int   Bexio contact ID (found).
   * @return null  No contact with this email exists in Bexio.
   * @return false API/network error — caller MUST abort, not fall through to create.
   */
  private static function search_by_email( string $email ): int|null|false {
    $payload = wp_json_encode( [
      [ 'field' => 'mail', 'value' => $email, 'criteria' => '=' ],
    ] );

    $res = pvbexio_api_call( 'POST', '2.0/contact/search', $payload );

    if ( empty( $res ) || (int) ( $res['status'] ?? 0 ) !== 200 ) {
      $http = $res['status'] ?? '?';
      $body = ! empty( $res['result'] ) ? wp_json_encode( $res['result'] ) : '(empty)';
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " Contact search failed (HTTP {$http}) for email {$email}. Body: {$body}"
      );
      return false; // Distinct from "not found" — caller must not create a contact.
    }

    // 200 with an empty array means no match found — not an error.
    if ( empty( $res['result'] ) || ! is_array( $res['result'] ) ) {
      return null;
    }

    return (int) $res['result'][0]->id;
  }

  /**
   * Creates a new Bexio contact from WC order billing data.
   *
   * Contact type:
   *   - billing_company is set  → contact_type_id 1 (company),  name_1 = company
   *   - no company              → contact_type_id 2 (person),   name_1 = last name,
   *                                                              name_2 = first name
   *
   * Address: WC billing_address_1 is a combined field (street + number).
   * We attempt to split it into Bexio's `street_name` and `house_number` by
   * matching a trailing house number (digits, optionally followed by a letter,
   * e.g. "Smith Street 77" or "Musterstrasse 12a"). If no number is found,
   * the entire string goes into `street_name` and `house_number` is omitted.
   * billing_address_2 maps to Bexio's `address_addition`.
   *
   * Returns the new Bexio contact ID, or null on failure.
   */
  /**
   * Compares WC billing against the Bexio contact and updates it (POST
   * 2.0/contact/{id}) only when something differs.
   *
   * @return string 'ok' | 'not-found' | 'retry'
   */
  private static function maybe_update_billing_address( int $contact_id, \WC_Order $order ): string {
    $wanted = self::build_billing_fields( $order );
    if ( $wanted === [] ) {
      return 'ok'; // Nothing to compare — keep existing contact as-is.
    }

    $res = pvbexio_api_call( 'GET', '2.0/contact/' . $contact_id );
    $http = (int) ( $res['status'] ?? 0 );
    if ( $http === 404 ) {
      return 'not-found'; // Contact deleted in Bexio — caller clears stale link.
    }
    if ( empty( $res ) || $http !== 200 || empty( $res['result'] ) ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " Could not fetch Bexio contact id={$contact_id} (HTTP "
        . ( $res['status'] ?? '?' ) . ') — aborting to retry later.'
      );
      return 'retry';
    }

    $current = $res['result'];
    $diff    = [];
    foreach ( $wanted as $field => $value ) {
      $existing = isset( $current->{$field} ) ? trim( (string) $current->{$field} ) : '';
      if ( $existing !== trim( (string) $value ) ) {
        $diff[ $field ] = $value;
      }
    }

    if ( $diff === [] ) {
      PingvinLogger::log(
        'info',
        self::LOG_PREFIX . " Billing address for Bexio contact id={$contact_id} unchanged — skipping update."
      );
      return 'ok';
    }

    $upd = pvbexio_api_call( 'POST', '2.0/contact/' . $contact_id, wp_json_encode( $diff ) );
    if ( empty( $upd ) || ! in_array( (int) ( $upd['status'] ?? 0 ), [ 200, 201 ], true ) ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " Billing update failed for Bexio contact id={$contact_id} (HTTP "
        . ( $upd['status'] ?? '?' ) . ') — aborting to retry later.'
      );
      return 'retry';
    }

    PingvinLogger::log(
      'info',
      self::LOG_PREFIX . ' Updated billing address for Bexio contact id=' . $contact_id
      . ' (fields: ' . implode( ',', array_keys( $diff ) ) . ').'
    );
    return 'ok';
  }

  /**
   * Builds Bexio contact billing fields from the WC order.
   * Shared by create_from_order() and maybe_update_billing_address().
   *
   * @return array Only non-empty fields.
   */
  private static function build_billing_fields( \WC_Order $order ): array {
    $first_name = trim( (string) $order->get_billing_first_name() );
    $last_name  = trim( (string) $order->get_billing_last_name() );
    $company    = trim( (string) $order->get_billing_company() );
    $email      = trim( (string) $order->get_billing_email() );
    $phone      = trim( (string) $order->get_billing_phone() );
    $address_1  = trim( (string) $order->get_billing_address_1() );
    $address_2  = trim( (string) $order->get_billing_address_2() );
    $city       = trim( (string) $order->get_billing_city() );
    $postcode   = trim( (string) $order->get_billing_postcode() );

    if ( $email === '' && $address_1 === '' && $city === '' && $postcode === '' && $company === '' && $last_name === '' ) {
      return [];
    }

    $is_company = $company !== '';
    $fields = [
      'name_1' => $is_company ? $company : ( $last_name ?: $email ),
    ];
    if ( ! $is_company && $first_name !== '' ) {
      $fields['name_2'] = $first_name;
    }
    if ( $email !== '' ) $fields['mail'] = $email;
    if ( $phone !== '' ) $fields['phone_fixed'] = $phone;

    // Split "Street Name 77a" → street_name + house_number.
    if ( $address_1 !== '' ) {
      if ( preg_match( '/^(.+?)\s+(\d+\s*[a-zA-Z]?)$/', $address_1, $m ) ) {
        $fields['street_name']  = trim( $m[1] );
        $fields['house_number'] = trim( $m[2] );
      } else {
        $fields['street_name'] = $address_1;
      }
    }

    if ( $address_2 !== '' ) $fields['address_addition'] = $address_2;
    if ( $postcode !== '' )  $fields['postcode']         = $postcode;
    if ( $city !== '' )      $fields['city']             = $city;

    return $fields;
  }

  private static function create_from_order( \WC_Order $order ): ?int {
    $email      = trim( (string) $order->get_billing_email() );
    $company    = trim( (string) $order->get_billing_company() );
    $contact    = self::build_billing_fields( $order );

    if ( $contact === [] ) {
      PingvinLogger::log( 'error', self::LOG_PREFIX . ' No billing data on order #' . $order->get_id() . ' — cannot create Bexio contact.' );
      return null;
    }

    $contact['contact_type_id'] = ( $company !== '' ) ? 1 : 2;

    $opts         = get_option( 'pvbexio_productsync_options', [] );
    $default_user = (int) ( $opts['pvbexio_default_user_id'] ?? 0 );

    if ( $default_user > 0 ) {
      $contact['user_id']  = $default_user;
      $contact['owner_id'] = $default_user;
    }

    $res = pvbexio_api_call( 'POST', '2.0/contact', wp_json_encode( $contact ) );

    if ( empty( $res ) || (int) ( $res['status'] ?? 0 ) !== 201 ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . ' Failed to create Bexio contact (HTTP '
        . ( $res['status'] ?? '?' ) . ") for email {$email}."
      );
      return null;
    }

    if ( empty( $res['result']->id ) ) {
      PingvinLogger::log( 'error', self::LOG_PREFIX . ' Bexio contact create returned no ID.' );
      return null;
    }

    return (int) $res['result']->id;
  }
}
