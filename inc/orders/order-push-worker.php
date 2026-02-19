<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvOrderPushWorker
 *
 * Pushes a single WooCommerce order to Bexio as a kb_order document.
 *
 * Flow
 * ----
 * 1. WC checkout fires `woocommerce_checkout_order_created` (hooked in main plugin).
 *    → `as_schedule_single_action` enqueues 'pv_push_order_to_bexio' with the order ID.
 * 2. Action Scheduler runs `run(int $order_id)`:
 *    a. Idempotency guard — skip if `_bexio_order_id` already saved on the order.
 *    b. Settings guard — abort if no default Bexio user configured.
 *    c. Contact resolution — PvContactPushHelper::ensure_bexio_contact().
 *    d. Position mapping — WC line items → KbPositionArticle / KbPositionCustom.
 *    e. POST 2.0/kb_order with minimal payload.
 *    f. Save `_bexio_order_id` and `_bexio_order_nr` as WC order meta.
 *
 * Tax mapping
 * -----------
 * Each line item's WC tax class is looked up in the plugin option
 * `pv_bexio_productsync_options` to find the matching Bexio tax ID
 * (the same mapping used by the product sync worker).
 * If no mapping is found, the position is sent without a tax_id.
 *
 * Meta keys written to WC order
 * ------------------------------
 * _bexio_order_id  — Bexio internal order ID (integer)
 * _bexio_order_nr  — Bexio human-readable order number (string)
 */
class PvOrderPushWorker {

  const AS_HOOK    = 'pv_push_order_to_bexio';
  const LOG_PREFIX = '[OrderPush]';

  /** Prevents duplicate add_action registrations across instances. */
  private static bool $hooked = false;

  public function __construct() {
    if ( ! self::$hooked ) {
      add_action( self::AS_HOOK, [ $this, 'run' ], 10, 1 );
      self::$hooked = true;
    }
  }

  /**
   * @param int $order_id  WooCommerce order ID.
   */
  public function run( int $order_id ): void {
    PingvinLogger::log( 'info', self::LOG_PREFIX . " Processing order #{$order_id}." );

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
      PingvinLogger::log( 'error', self::LOG_PREFIX . " Order #{$order_id} not found — aborting." );
      return;
    }

    if ( $order->get_meta( '_bexio_order_id', true ) ) {
      PingvinLogger::log(
        'info',
        self::LOG_PREFIX . " Order #{$order_id} already pushed (bexio_order_id="
        . $order->get_meta( '_bexio_order_id', true ) . ") — skipping."
      );
      return;
    }

    $opts         = get_option( 'pv_bexio_productsync_options', [] );
    $default_user = (int) ( $opts['pv_bexio_default_user_id'] ?? 0 );

    if ( $default_user === 0 ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " No default Bexio user configured — cannot push order #{$order_id}. "
        . 'Set a default user in the plugin settings.'
      );
      $order->update_meta_data( '_bexio_push_status', 'failed' );
      $order->save_meta_data();
      return; // Config error — no point retrying until settings are fixed.
    }

    $contact_id = PvContactPushHelper::ensure_bexio_contact( $order );

    if ( $contact_id === null ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " Could not resolve Bexio contact for order #{$order_id} — aborting."
      );
      throw new \RuntimeException(
        esc_html( self::LOG_PREFIX . " Aborted: contact resolution failed (order #{$order_id})." )
      );
    }

    $tax_id_map = $this->build_tax_id_map( $opts );

    if ( ! isset( $tax_id_map[''] ) ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " No standard Bexio tax rate configured — cannot push order #{$order_id}. "
        . 'Map the standard WC tax rate in the plugin settings.'
      );
      $order->update_meta_data( '_bexio_push_status', 'failed' );
      $order->save_meta_data();
      return; // Config error — no point retrying until settings are fixed.
    }

    $positions = $this->build_positions( $order, $tax_id_map );

    // mwst_type:
    //   0 = taxes apply  (use when WC tax is enabled)
    //   2 = exempt        (use when WC tax is globally disabled)
    // mwst_is_net:
    //   WC's $item->get_total() always returns the ex-tax line total,
    //   so our unit_prices are always net — Bexio must add tax on top.
    $taxes_enabled = wc_tax_enabled();
    $payload = [
      'title'       => $this->build_title( $order ),
      'contact_id'  => $contact_id,
      'user_id'     => $default_user,
      'mwst_type'   => $taxes_enabled ? 0 : 2,
      'mwst_is_net' => true,
      'positions'   => $positions,
    ];

    $res = pv_api_call( 'POST', '2.0/kb_order', wp_json_encode( $payload ) );

    if ( empty( $res ) || (int) ( $res['status'] ?? 0 ) !== 201 ) {
      $http = $res['status'] ?? '?';
      $body = ! empty( $res['result'] ) ? wp_json_encode( $res['result'] ) : '(empty)';
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . " POST 2.0/kb_order failed (HTTP {$http}) for order #{$order_id}. Body: {$body}"
      );
      throw new \RuntimeException(
        esc_html( self::LOG_PREFIX . " kb_order POST returned HTTP {$http} for order #{$order_id}." )
      );
    }

    $bexio_order = $res['result'];

    if ( empty( $bexio_order->id ) ) {
      PingvinLogger::log( 'error', self::LOG_PREFIX . " Bexio returned no order ID for order #{$order_id}." );
      throw new \RuntimeException( esc_html( self::LOG_PREFIX . " No ID in Bexio response (order #{$order_id})." ) );
    }

    $order->update_meta_data( '_bexio_order_id', (int) $bexio_order->id );
    $order->update_meta_data( '_bexio_order_nr', (string) ( $bexio_order->document_nr ?? '' ) );
    $order->update_meta_data( '_bexio_order_status', (string) ( $bexio_order->kb_item_status_id ?? '' ) );
    $order->update_meta_data( '_bexio_push_status', 'success' );
    $order->update_meta_data( 'pv_bexio_sync', [ 'last_sync' => current_time( 'Y-m-d H:i:s' ) ] );
    $order->save_meta_data();

    PingvinLogger::log(
      'info',
      self::LOG_PREFIX . " Order #{$order_id} pushed successfully."
      . " bexio_id={$bexio_order->id} bexio_nr=" . ( $bexio_order->document_nr ?? '?' ) . '.'
    );
  }

  /**
   * Maps WC order line items to Bexio kb_order positions.
   *
   * Each item becomes either:
   *   KbPositionArticle — when the WC product has `_bexio_product_id` meta
   *   KbPositionCustom  — for everything else (no Bexio article match)
   *
   * @param \WC_Order $order
   * @param array     $tax_id_map  [ wc_tax_class => bexio_tax_id ]
   * @return array
   */
  private function build_positions( \WC_Order $order, array $tax_id_map ): array {
    $positions = [];

    foreach ( $order->get_items() as $item ) {
      /** @var \WC_Order_Item_Product $item */
      $product = $item->get_product();
      $qty     = (float) $item->get_quantity();
      $name    = $item->get_name();

      // Skip zero-qty items — semantically invalid and Bexio may reject them.
      if ( $qty <= 0 ) {
        PingvinLogger::log(
          'warning',
          self::LOG_PREFIX . " Skipping line item '{$name}' with qty {$qty} on order #{$order->get_id()}."
        );
        continue;
      }

      // Use the actual line total ÷ qty to get the effective unit price
      // (already includes any per-item discounts).
      $unit_price = round( (float) $item->get_total() / $qty, 4 );

      // Resolve tax class → Bexio tax ID.
      // Read from the order item (recorded at purchase time), not the current
      // product — the product's tax class could have changed since the order
      // was placed. WC stores the standard rate as '' on items.
      $wc_tax_class = $item->get_tax_class();
      if ( $wc_tax_class === 'standard' ) {
        $wc_tax_class = '';
      }
      
      if ( isset( $tax_id_map[ $wc_tax_class ] ) ) {
        $tax_id = $tax_id_map[ $wc_tax_class ];
      } else {
        PingvinLogger::log(
          'warning',
          self::LOG_PREFIX . " No Bexio tax mapping for WC class '{$wc_tax_class}' (item: '{$name}', order #{$order->get_id()}) — falling back to standard rate."
        );
        $tax_id = $tax_id_map[''];
      }

      $bexio_product_id = $product
        ? (int) $product->get_meta( '_bexio_id', true )
        : 0;

      if ( $bexio_product_id > 0 ) {
        $pos = [
          'type'       => 'KbPositionArticle',
          'article_id' => $bexio_product_id,
          'amount'     => $qty,
          'unit_price' => $unit_price,
          'tax_id'     => $tax_id,
          'text'       => $name,
        ];
      } else {
        $pos = [
          'type'       => 'KbPositionCustom',
          'amount'     => $qty,
          'unit_price' => $unit_price,
          'tax_id'     => $tax_id,
          'text'       => $name,
        ];
      }

      $positions[] = $pos;
    }

    foreach ( $order->get_items( 'fee' ) as $fee ) {
      /** @var \WC_Order_Item_Fee $fee */
      $fee_total = (float) $fee->get_total();
      if ( $fee_total == 0.0 ) {
        continue;
      }
      $fee_tax_class = $fee->get_tax_class();
      if ( $fee_tax_class === 'standard' ) {
        $fee_tax_class = '';
      }
      $positions[] = [
        'type'       => 'KbPositionCustom',
        'amount'     => 1,
        'unit_price' => $fee_total,
        'tax_id'     => $tax_id_map[ $fee_tax_class ] ?? $tax_id_map[''],
        'text'       => $fee->get_name() ?: __( 'Gebühr', 'pingvin-bexio-sync' ),
      ];
    }

    $shipping_total = (float) $order->get_shipping_total();
    if ( $shipping_total > 0 ) {
      $positions[] = [
        'type'       => 'KbPositionCustom',
        'amount'     => 1,
        'unit_price' => $shipping_total,
        'tax_id'     => $tax_id_map[''], // Standard rate; guard already passed above.
        'text'       => (string) ( $order->get_shipping_method() ?: __( 'Versand', 'pingvin-bexio-sync' ) ),
      ];
    }

    return $positions;
  }

  /**
   * Builds a map of [ wc_tax_class => bexio_tax_id (int) ] from plugin settings.
   *
   * The plugin options store one Bexio tax ID for each of the three WC
   * tax rate tiers (standard / reduced / special). We invert this so
   * the position builder can look up by WC tax class.
   *
   * WC standard tax class is stored as an empty string ('').
   *
   * @param array $opts  pv_bexio_productsync_options
   * @return array
   */
  private function build_tax_id_map( array $opts ): array {
    $map = [];

    $pairs = [
      // [ wc_class_option_key,                     bexio_tax_id_option_key                  ]
      [ 'pv_productsync_tax_rate_standard_woo',  'pv_productsync_tax_rate_standard_bexio' ],
      [ 'pv_productsync_tax_rate_reduced_woo',   'pv_productsync_tax_rate_reduced_bexio'  ],
      [ 'pv_productsync_tax_rate_special_woo',   'pv_productsync_tax_rate_special_bexio'  ],
    ];

    foreach ( $pairs as [ $wc_key, $bexio_key ] ) {
      $wc_class     = (string) ( $opts[ $wc_key ]    ?? '' );
      $bexio_tax_id = (int)    ( $opts[ $bexio_key ] ?? 0  );

      if ( $bexio_tax_id > 0 ) {
        // WC stores the standard tax class as '' on order items,
        // but the settings dropdown may save the literal slug 'standard'.
        // Normalise so the lookup key is always ''.
        $map_key         = ( $wc_class === 'standard' || $wc_class === '' ) ? '' : $wc_class;
        $map[ $map_key ] = $bexio_tax_id;
      }
    }

    return $map;
  }

  /**
   * Builds a human-readable title for the Bexio order document.
   * Format: "Bestellung #{order_number}" or falls back to the order ID.
   */
  private function build_title( \WC_Order $order ): string {
    $nr = $order->get_order_number();
    return __( 'Bestellung', 'pingvin-bexio-sync' ) . ' #' . ( $nr ?: $order->get_id() );
  }

  /**
   * Schedules a single push action for the given order.
   * Called from the `woocommerce_checkout_order_created` hook.
   *
   * @param int $order_id
   */
  public static function enqueue( int $order_id ): void {
    if ( \as_has_scheduled_action( self::AS_HOOK, [ 'order_id' => $order_id ], 'pv_sync' ) ) {
      PingvinLogger::log(
        'info',
        self::LOG_PREFIX . " Push already queued for order #{$order_id} — skipping duplicate."
      );
      return;
    }

    $order = wc_get_order( $order_id );
    if ( $order ) {
      $order->update_meta_data( '_bexio_push_status', 'pending' );
      $order->save_meta_data();
    }

    $action_id = \as_schedule_single_action(
      time(),
      self::AS_HOOK,
      [ 'order_id' => $order_id ],
      'pv_sync'
    );

    PingvinLogger::log(
      'info',
      self::LOG_PREFIX . " Enqueued push for order #{$order_id} (action id: {$action_id})."
    );
  }
}
