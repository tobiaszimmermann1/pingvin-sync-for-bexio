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
 * 1. WP user meta `_bexio_contact_id`   → instant return (already linked)
 * 2. POST 2.0/contact/search by email   → link + return if found in Bexio
 * 3. POST 2.0/contact                   → create in Bexio, link, return ID
 * 4. Returns null on any API failure — order push should be aborted.
 *
 * For guest orders (user_id = 0) the meta-save steps are skipped; the
 * Bexio contact ID is returned for use in the order payload only.
 */
class PvContactPushHelper {

  const LOG_PREFIX = '[ContactPush]';

  // ---------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------

  /**
   * Resolves (or creates) a Bexio contact for the given WC order.
   *
   * @param \WC_Order $order
   * @return int|null  Bexio contact ID, or null on failure.
   */
  public static function ensure_bexio_contact( \WC_Order $order ): ?int {
    $user_id       = (int) $order->get_user_id();
    $billing_email = trim( (string) $order->get_billing_email() );

    // 1. Logged-in user already linked → return immediately.
    if ( $user_id > 0 ) {
      $cached = get_user_meta( $user_id, '_bexio_contact_id', true );
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

    // 2. Search Bexio for an existing contact with this email.
    $found_id = self::search_by_email( $billing_email );

    if ( $found_id !== null ) {
      if ( $user_id > 0 ) {
        update_user_meta( $user_id, '_bexio_contact_id', $found_id );
      }
      PingvinLogger::log(
        'info',
        self::LOG_PREFIX . " Found existing Bexio contact id={$found_id} for email {$billing_email}."
      );
      return $found_id;
    }

    // 3. Create a new contact in Bexio from the order billing data.
    $created_id = self::create_from_order( $order );

    if ( $created_id !== null ) {
      if ( $user_id > 0 ) {
        update_user_meta( $user_id, '_bexio_contact_id', $created_id );
      }
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

  // ---------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------

  /**
   * Searches Bexio for a contact with the given email.
   *
   * Returns the first matching Bexio contact ID, or null if not found
   * or if the API call fails.
   */
  private static function search_by_email( string $email ): ?int {
    $payload = wp_json_encode( [
      [ 'field' => 'mail', 'value' => $email, 'criteria' => '=' ],
    ] );

    $res = pv_api_call( 'POST', '2.0/contact/search', $payload );

    if ( empty( $res ) || (int) ( $res['status'] ?? 0 ) !== 200 ) {
      PingvinLogger::log(
        'warning',
        self::LOG_PREFIX . ' Contact search request failed (HTTP '
        . ( $res['status'] ?? '?' ) . ") for email {$email}."
      );
      return null;
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

    $opts           = get_option( 'pv_bexio_productsync_options', [] );
    $default_user   = (int) ( $opts['pv_bexio_default_user_id'] ?? 0 );

    $contact = [
      'contact_type_id' => $is_company ? 1 : 2,
      'name_1'          => $is_company ? $company    : ( $last_name ?: $email ),
      'name_2'          => $is_company ? $first_name : $first_name,
      'mail'            => $email,
    ];

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

    $res = pv_api_call( 'POST', '2.0/contact', wp_json_encode( $contact ) );

    // Bexio returns 201 Created on success.
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
