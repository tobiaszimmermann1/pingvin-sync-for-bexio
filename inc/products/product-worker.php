<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvProductSyncWorker
 *
 * Handles one batch of BATCH_SIZE products per Action Scheduler run.
 *
 * Flow:
 *   1. Receives $offset from Action Scheduler.
 *   2. Fetches articles [offset … offset+BATCH_SIZE) from Bexio API.
 *   3. Upserts each product in WooCommerce.
 *   4. Writes next_offset + last_worker_at into sync state.
 *   5. If the batch was full (BATCH_SIZE items) → enqueues the next worker.
 *      If the batch was short (< BATCH_SIZE items) → marks the cycle complete.
 */
class PvProductSyncWorker {

  /** Mirrors the WORKER_HOOK constant in PvProductSyncEngine for convenience. */
  const HOOK       = 'pv_sync_worker_products';
  const BATCH_SIZE = 10;

  /** Prevents registering add_action more than once across all instances. */
  private static bool $hooked = false;

  public function __construct() {
    if ( ! self::$hooked ) {
      add_action( self::HOOK, [ $this, 'run' ], 10, 1 );
      self::$hooked = true;
    }
  }

  // ---------------------------------------------------------------
  // Main entry point (called by Action Scheduler)
  // ---------------------------------------------------------------

  /**
   * @param int $offset  Zero-based product offset within the full Bexio catalog.
   */
  public function run( int $offset ): void {
    PingvinLogger::log( 'info', "[ProductWorker] Batch start — offset: $offset." );

    $engine = new PvProductSyncEngine();
    $state  = $engine->get_state();

    // Safety: abort if the cycle was cancelled between enqueueing and running.
    if ( $state['status'] !== 'running' ) {
      PingvinLogger::log(
        'info',
        "[ProductWorker] Cycle no longer running (status: {$state['status']}). Aborting at offset $offset."
      );
      return;
    }

    // Heartbeat: record that this worker is alive RIGHT NOW and advance
    // next_offset to offset+BATCH_SIZE immediately.
    // This prevents the watchdog from:
    //   (a) thinking the chain is broken while we are still running, and
    //   (b) re-enqueueing the *same* offset if it does fire mid-run.
    $engine->set_state( [
      'last_worker_at' => time(),
      'next_offset'    => $offset + self::BATCH_SIZE,
    ] );

    // ---------------------------------------------------------------
    // 1. Fetch batch from Bexio
    // ---------------------------------------------------------------
    $products = $this->fetch_bexio_batch( $offset );

    if ( $products === null ) {
      // Record the error in state so the UI reflects it immediately, then
      // throw so AS marks this action as failed and schedules a retry.
      $error_msg = "[ProductWorker] Bexio API call failed at offset $offset. AS will retry.";
      $engine->set_state( [ 'last_error' => $error_msg ] );
      throw new \RuntimeException( $error_msg );
    }

    $count = count( $products );
    PingvinLogger::log( 'info', "[ProductWorker] Fetched $count products from Bexio (offset: $offset)." );

    // ---------------------------------------------------------------
    // 2. Build tax map (once per batch — cheap, always fresh)
    // ---------------------------------------------------------------
    $plugin_opts = get_option( 'pv_bexio_productsync_options', [] );
    $tax_map     = $this->build_tax_map( $plugin_opts );

    // ---------------------------------------------------------------
    // 3. Upsert each product in WooCommerce
    // ---------------------------------------------------------------
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $failed  = 0;
    foreach ( $products as $bexio_product ) {
      try {
        $result = $this->upsert_product( $bexio_product, $tax_map, $plugin_opts );
        switch ( $result ) {
          case 'created': $created++; break;
          case 'updated': $updated++; break;
          case 'skipped': $skipped++; break;
          default:        $failed++;  break;
        }
      } catch ( \Throwable $e ) {
        $failed++;
        // Log but never let one bad product kill the whole batch.
        PingvinLogger::log(
          'error',
          "[ProductWorker] Exception processing product #{$bexio_product->id} "
          . "({$bexio_product->intern_name}): " . $e->getMessage()
        );
      }
    }

    PingvinLogger::log( 'info', "[ProductWorker] Batch complete — created: $created, updated: $updated, skipped: $skipped, failed: $failed." );

    // ---------------------------------------------------------------
    // 4. Update state + decide whether to continue
    // ---------------------------------------------------------------
    $this->finish_batch( $engine, $offset, $count, $created + $updated, $skipped, $failed );
  }

  // ---------------------------------------------------------------
  // Post-batch bookkeeping
  // ---------------------------------------------------------------

  /**
   * Updates sync state and enqueues the next worker if the batch was full.
   *
   * @param PvProductSyncEngine $engine
   * @param int               $offset   The offset this batch started at.
   * @param int               $fetched  Items returned by Bexio (drives last-batch detection).
   * @param int               $upserted Items actually saved to WC (created + updated).
   * @param int               $skipped  Items skipped because Bexio data was unchanged.
   * @param int               $failed   Items that failed to save.
   */
  private function finish_batch(
    PvProductSyncEngine $engine,
    int $offset,
    int $fetched,
    int $upserted,
    int $skipped,
    int $failed
  ): void {
    $state         = $engine->get_state();
    $completed     = (int) $state['completed_batches'] + 1;
    $total_p       = (int) $state['total_processed']   + $upserted;
    $total_skipped = (int) ( $state['skipped_count']   ?? 0 ) + $skipped;
    $total_failed  = (int) ( $state['failed_count']    ?? 0 ) + $failed;
    $next          = $offset + self::BATCH_SIZE;

    // Last batch = Bexio returned fewer items than the page size.
    $is_last_batch = ( $fetched < self::BATCH_SIZE );

    if ( $is_last_batch ) {
      // Cycle complete.
      PingvinLogger::log(
        'info',
        "[ProductWorker] Last batch reached (fetched: $fetched). Cycle complete — "
        . "upserted: $total_p, skipped: $total_skipped, failed: $total_failed."
      );
      $engine->set_state( [
        'status'            => 'idle',
        'next_offset'       => null,
        'completed_batches' => $completed,
        'total_processed'   => $total_p,
        'skipped_count'     => $total_skipped,
        'failed_count'      => $total_failed,
        'last_worker_at'    => time(),
        'last_completed_at' => time(),
        'last_error'        => null,
      ] );
    } else {
      // More batches to go — enqueue the next worker.
      PingvinLogger::log(
        'info',
        "[ProductWorker] Batch done (fetched: $fetched, upserted: $upserted, skipped: $skipped, failed: $failed). Enqueueing next at offset $next."
      );
      $engine->set_state( [
        'next_offset'       => $next,
        'completed_batches' => $completed,
        'total_processed'   => $total_p,
        'skipped_count'     => $total_skipped,
        'failed_count'      => $total_failed,
        'last_worker_at'    => time(),
      ] );
      self::enqueue_worker( $next );
    }
  }

  // ---------------------------------------------------------------
  // Worker enqueueing helper (shared by coordinator and workers)
  // ---------------------------------------------------------------

  /**
   * Enqueues a worker action for the given offset.
   *
   * We deliberately do NOT use unique=true here: AS returns 0 (silently
   * drops the action) when it considers a matching action already exists,
   * which broke the chain in practice.  The state machine already prevents
   * real overlap — idempotent upserts handle the rare duplicate gracefully.
   *
   * @param int  $offset
   * @param bool $unique  Pass true only from the coordinator watchdog.
   */
  public static function enqueue_worker( int $offset, bool $unique = false ): void {
    $action_id = as_schedule_single_action(
      time(),
      self::HOOK,
      [ 'offset' => $offset ],
      'pv_sync',
      $unique
    );

    if ( ! $action_id ) {
      // AS returned 0 — log clearly so it is visible in the log.
      PingvinLogger::log(
        'error',
        "[ProductWorker] as_schedule_single_action returned 0 for offset $offset "
        . '(unique=' . ( $unique ? 'true' : 'false' ) . '). '
        . 'The coordinator watchdog will re-enqueue on the next tick.'
      );
      return;
    }

    PingvinLogger::log( 'info', "[ProductWorker] Worker enqueued (id: $action_id, offset: $offset)." );
  }

  // ---------------------------------------------------------------
  // Bexio API fetch
  // ---------------------------------------------------------------

  /**
   * Fetches one page of articles from Bexio.
   *
   * Returns an array of stdClass objects, or null on API failure.
   *
   * @param int $offset
   * @return array|null
   */
  private function fetch_bexio_batch( int $offset ): ?array {
    $endpoint = '2.0/article?offset=' . $offset . '&limit=' . self::BATCH_SIZE;
    $res      = pv_api_call( 'GET', $endpoint );

    if ( empty( $res ) || ! isset( $res['status'] ) ) {
      PingvinLogger::log( 'error', "[ProductWorker] No response from Bexio API (offset: $offset)." );
      return null;
    }

    if ( (int) $res['status'] !== 200 ) {
      PingvinLogger::log(
        'error',
        "[ProductWorker] Bexio API returned HTTP {$res['status']} (offset: $offset)."
      );
      return null;
    }

    $items = $res['result'];

    if ( ! is_array( $items ) ) {
      PingvinLogger::log( 'error', "[ProductWorker] Unexpected Bexio response format (offset: $offset)." );
      return null;
    }

    return $items;
  }

  // ---------------------------------------------------------------
  // Tax map builder
  // ---------------------------------------------------------------

  /**
   * Builds a map of [ bexio_tax_id => wc_tax_class ] from the Bexio
   * taxes endpoint combined with the plugin settings.
   *
   * Returns an empty array if the tax API call fails — sync continues
   * without tax mapping rather than aborting the whole batch.
   *
   * @return array
   */
  private function build_tax_map( array $plugin_opts ): array {
    $standard_bexio = $plugin_opts['pv_productsync_tax_rate_standard_bexio'] ?? null;
    $reduced_bexio  = $plugin_opts['pv_productsync_tax_rate_reduced_bexio']  ?? null;
    $special_bexio  = $plugin_opts['pv_productsync_tax_rate_special_bexio']  ?? null;

    $standard_woo   = $plugin_opts['pv_productsync_tax_rate_standard_woo']   ?? '';
    $reduced_woo    = $plugin_opts['pv_productsync_tax_rate_reduced_woo']    ?? '';
    $special_woo    = $plugin_opts['pv_productsync_tax_rate_special_woo']    ?? '';

    $res = pv_api_call( 'GET', '3.0/taxes' );
    if ( empty( $res['result'] ) || ! is_array( $res['result'] ) ) {
      PingvinLogger::log( 'warning', '[ProductWorker] Could not fetch Bexio tax rates — tax classes will be skipped.' );
      return [];
    }

    $map = [];
    foreach ( $res['result'] as $tax ) {
      // Settings now store the Bexio tax ID (integer), not the code string.
      if ( (string) $tax->id === (string) $standard_bexio ) $map[ $tax->id ] = $standard_woo;
      if ( (string) $tax->id === (string) $reduced_bexio  ) $map[ $tax->id ] = $reduced_woo;
      if ( (string) $tax->id === (string) $special_bexio  ) $map[ $tax->id ] = $special_woo;
    }

    return $map;
  }

  // ---------------------------------------------------------------
  // WooCommerce upsert
  // ---------------------------------------------------------------

  /**
   * Creates or updates a single WooCommerce product from a Bexio article.
   *
   * Skip-if-unchanged: for existing products a md5 hash of the raw Bexio
   * payload is compared against `_bexio_hash` stored on the WC product.
   * If identical, no DB writes occur and 'skipped' is returned.
   *
   * @param object $p           Raw Bexio article object.
   * @param array  $tax_map     [ bexio_tax_id => wc_tax_class ] map.
   * @param array  $plugin_opts Plugin options array.
   * @return string  'created' | 'updated' | 'skipped' | 'failed'
   */
  private function upsert_product( object $p, array $tax_map, array $plugin_opts ): string {
    $is_new    = false;
    $wc_id     = wc_get_product_id_by_sku( $p->intern_code );

    if ( $wc_id ) {
      $product = wc_get_product( $wc_id );
      if ( ! $product ) {
        PingvinLogger::log( 'error', "[ProductWorker] Could not load WC product id $wc_id for SKU {$p->intern_code}." );
        return 'failed';
      }

      // Skip-if-unchanged: if the Bexio payload hasn't changed since the last
      // sync there is nothing to write — skip all DB work entirely.
      $incoming_hash = md5( wp_json_encode( $p ) );
      if ( $product->get_meta( '_bexio_hash', true ) === $incoming_hash ) {
        return 'skipped';
      }
    } else {
      $is_new  = true;
      $product = new \WC_Product_Simple();
    }

    // --- Core fields ------------------------------------------------
    $product->set_name( (string) ( $p->intern_name        ?? '' ) );
    $product->set_description( (string) ( $p->intern_description ?? '' ) );
    $product->set_sku( (string) $p->intern_code );
    $product->set_status( 'publish' );

    // --- Price ------------------------------------------------------
    $sale_price = (float) ( $p->sale_price ?? 0 );
    $product->set_regular_price( (string) $sale_price );

    // --- Weight (Bexio stores in grams) -----------------------------
    $weight = (float) ( $p->weight ?? 0 );
    if ( $weight > 0 ) {
      $product->set_weight( (string) $this->convert_weight( $weight, get_option( 'woocommerce_weight_unit', 'kg' ) ) );
    }

    // --- Dimensions (Bexio stores in mm) ----------------------------
    $wc_dim = get_option( 'woocommerce_dimension_unit', 'cm' );
    $width  = (float) ( $p->width  ?? 0 );
    $height = (float) ( $p->height ?? 0 );
    if ( $width  > 0 ) $product->set_width(  (string) $this->convert_dimension( $width,  $wc_dim ) );
    if ( $height > 0 ) $product->set_height( (string) $this->convert_dimension( $height, $wc_dim ) );

    // --- Stock ------------------------------------------------------
    if ( ! empty( $p->is_stock ) ) {
      $stock_qty = (int) round( (float) ( $p->stock_available_nr ?? 0 ) );
      $product->set_manage_stock( true );
      $product->set_stock_quantity( $stock_qty );
      $product->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );
      if ( isset( $p->stock_min_nr ) ) {
        $product->set_low_stock_amount( (int) $p->stock_min_nr );
      }
    } else {
      $product->set_manage_stock( false );
      $product->set_stock_status( 'instock' );
    }

    // --- Tax --------------------------------------------------------
    if (
      get_option( 'woocommerce_calc_taxes' ) === 'yes'
      && ! empty( $tax_map )
      && isset( $p->tax_income_id )
      && isset( $tax_map[ $p->tax_income_id ] )
    ) {
      $wc_tax_class = $tax_map[ $p->tax_income_id ];
      $product->set_tax_status( 'taxable' );
      $product->set_tax_class( $wc_tax_class );

      // If WC stores prices including tax, mark up the Bexio ex-tax price.
      if ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' ) {
        $sale_price = $this->add_tax_to_price( $sale_price, $wc_tax_class );
        $product->set_regular_price( (string) $sale_price );
      }
    }

    // --- Meta: individual Bexio fields ------------------------------
    $product->update_meta_data( '_bexio_id',            $p->id                    ?? null );
    $product->update_meta_data( '_user_id',             $p->user_id               ?? null );
    $product->update_meta_data( '_article_type_id',     $p->article_type_id       ?? null );
    $product->update_meta_data( '_contact_id',          $p->contact_id            ?? null );
    $product->update_meta_data( '_deliverer_code',      $p->deliverer_code        ?? null );
    $product->update_meta_data( '_deliverer_name',      $p->deliverer_name        ?? null );
    $product->update_meta_data( '_deliverer_description', $p->deliverer_description ?? null );
    $product->update_meta_data( '_purchase_price',      $p->purchase_price        ?? null );
    $product->update_meta_data( '_purchase_total',      $p->purchase_total        ?? null );
    $product->update_meta_data( '_sale_total',          $p->sale_total            ?? null );
    $product->update_meta_data( '_currency_id',         $p->currency_id           ?? null );
    $product->update_meta_data( '_tax_income_id',       $p->tax_income_id         ?? null );
    $product->update_meta_data( '_tax_id',              $p->tax_id                ?? null );
    $product->update_meta_data( '_tax_expense_id',      $p->tax_expense_id        ?? null );
    $product->update_meta_data( '_unit_id',             $p->unit_id               ?? null );
    $product->update_meta_data( '_stock_id',            $p->stock_id              ?? null );
    $product->update_meta_data( '_stock_place_id',      $p->stock_place_id        ?? null );
    $product->update_meta_data( '_stock_nr',            $p->stock_nr              ?? null );
    $product->update_meta_data( '_stock_min_nr',        $p->stock_min_nr          ?? null );
    $product->update_meta_data( '_stock_reserved_nr',   $p->stock_reserved_nr     ?? null );
    $product->update_meta_data( '_stock_picked_nr',     $p->stock_picked_nr       ?? null );
    $product->update_meta_data( '_stock_disposed_nr',   $p->stock_disposed_nr     ?? null );
    $product->update_meta_data( '_stock_ordered_nr',    $p->stock_ordered_nr      ?? null );
    $product->update_meta_data( '_volume',              $p->volume                ?? null );
    $product->update_meta_data( '_remarks',             $p->remarks               ?? null );
    $product->update_meta_data( '_delivery_price',      $p->delivery_price        ?? null );
    $product->update_meta_data( '_article_group_id',    $p->article_group_id      ?? null );

    // --- Meta: hash (staleness guard) + full JSON cache -------------
    $product->update_meta_data( '_bexio_hash', md5( wp_json_encode( $p ) ) );
    $product->update_meta_data( '_bexio_data', wp_json_encode( [
      'last_sync' => current_time( 'Y-m-d H:i:s' ),
      'data'      => $p,
    ] ) );

    // --- Save -------------------------------------------------------
    $saved_id = $product->save();

    if ( is_wp_error( $saved_id ) || ! $saved_id ) {
      PingvinLogger::log(
        'error',
        "[ProductWorker] Failed to save WC product for Bexio article #{$p->id} ({$p->intern_name})."
      );
      return 'failed';
    }

    PingvinLogger::log(
      'info',
      '[ProductWorker] ' . ( $is_new ? 'Created' : 'Updated' )
      . " WC product (id: $saved_id / sku: {$p->intern_code} / name: {$p->intern_name})."
    );

    return $is_new ? 'created' : 'updated';
  }

  // ---------------------------------------------------------------
  // Unit conversion helpers
  // ---------------------------------------------------------------

  /**
   * Converts grams (Bexio unit) to the WooCommerce weight unit.
   */
  private function convert_weight( float $grams, string $wc_unit ): float {
    switch ( $wc_unit ) {
      case 'kg':  return $grams / 1000;
      case 'g':   return $grams;
      case 'lbs': return $grams / 453.592;
      case 'oz':  return $grams / 28.3495;
      default:    return $grams / 1000;
    }
  }

  /**
   * Converts millimetres (Bexio unit) to the WooCommerce dimension unit.
   */
  private function convert_dimension( float $mm, string $wc_unit ): float {
    switch ( $wc_unit ) {
      case 'cm': return $mm / 10;
      case 'm':  return $mm / 1000;
      case 'mm': return $mm;
      case 'in': return $mm / 25.4;
      case 'yd': return $mm / 914.4;
      default:   return $mm / 10;
    }
  }

  // ---------------------------------------------------------------
  // Tax price helper
  // ---------------------------------------------------------------

  /**
   * Adds the tax amount to a net price using the actual WC tax rates
   * for the given tax class — no hardcoded percentages.
   *
   * @param float  $net_price
   * @param string $wc_tax_class
   * @return float  Price including tax.
   */
  private function add_tax_to_price( float $net_price, string $wc_tax_class ): float {
    $rates = \WC_Tax::get_rates_for_tax_class( $wc_tax_class );
    if ( empty( $rates ) ) {
      return $net_price;
    }

    // Sum all applicable rates (e.g. state + federal).
    $total_rate = 0.0;
    foreach ( $rates as $rate ) {
      $total_rate += (float) $rate->tax_rate;
    }

    return $net_price * ( 1 + $total_rate / 100 );
  }
}
