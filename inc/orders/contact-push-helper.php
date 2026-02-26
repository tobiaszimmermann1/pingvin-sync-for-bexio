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
 * 1. WP user meta `_pvbexio_contact_id`   → instant return (already linked)
 * 2. POST 2.0/contact/search by email   → link + return if found in Bexio
 * 3. POST 2.0/contact                   → create in Bexio, link, return ID
 * 4. Returns null on any API failure — order push should be aborted.
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
        PingvinLogger::log(
          'info',
          self::LOG_PREFIX . " Using cached Bexio contact id={$cached} for WP user {$user_id} (order #{$order->get_id()})."
        );
        return (int) $cached;
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
  private static function create_from_order( \WC_Order $order ): ?int {
    $first_name = trim( (string) $order->get_billing_first_name() );
    $last_name  = trim( (string) $order->get_billing_last_name() );
    $company    = trim( (string) $order->get_billing_company() );
    $email      = trim( (string) $order->get_billing_email() );
    $phone      = trim( (string) $order->get_billing_phone() );
    $address_1  = trim( (string) $order->get_billing_address_1() );
    $address_2  = trim( (string) $order->get_billing_address_2() );
    $city       = trim( (string) $order->get_billing_city() );
    $postcode   = trim( (string) $order->get_billing_postcode() );

    $is_company = $company !== '';

    $opts           = get_option( 'pvbexio_productsync_options', [] );
    $default_user   = (int) ( $opts['pvbexio_default_user_id'] ?? 0 );

    $contact = [
      'contact_type_id' => $is_company ? 1 : 2,
      'name_1'          => $is_company ? $company : ( $last_name ?: $email ),
      'mail'            => $email,
    ];
    // For person contacts name_2 = first name.
    // For company contacts name_2 is a subtitle/division field in Bexio — do not populate it with a person name.
    if ( ! $is_company && $first_name !== '' ) {
      $contact['name_2'] = $first_name;
    }

    if ( $default_user > 0 ) {
      $contact['user_id']  = $default_user;
      $contact['owner_id'] = $default_user;
    }

    if ( $phone !== '' )    $contact['phone_fixed'] = $phone;

    // Split "Street Name 77a" → street_name + house_number.
    if ( $address_1 !== '' ) {
      if ( preg_match( '/^(.+?)\s+(\d+\s*[a-zA-Z]?)$/', $address_1, $m ) ) {
        $contact['street_name']  = trim( $m[1] );
        $contact['house_number'] = trim( $m[2] );
      } else {
        $contact['street_name'] = $address_1;
      }
    }

    if ( $address_2 !== '' ) $contact['address_addition'] = $address_2;
    if ( $postcode !== '' )  $contact['postcode']         = $postcode;
    if ( $city !== '' )      $contact['city']             = $city;

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
