<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvProductSyncEngine
 *
 * Concrete engine for the Bexio → WooCommerce product sync.
 * All coordinator / watchdog / state-machine logic lives in PvAbstractSyncEngine.
 * This class only supplies the six required constants and the two abstract
 * worker-coupling methods.
 */
class PvProductSyncEngine extends PvAbstractSyncEngine {

  const STATE_KEY        = 'pv_sync_state_products';
  const COORDINATOR_HOOK = 'pv_sync_coordinator';
  const WORKER_HOOK      = 'pv_sync_worker_products';
  const SETTINGS_KEY     = 'pv_bexio_productsync_action_settings';
  const NEXT_TRANSIENT   = 'pv_bexio_connector_next';
  const LOG_PREFIX       = '[ProductEngine]';

  protected function enqueue_first_worker(): void {
    PvProductSyncWorker::enqueue_worker( 0 );
  }

  protected function enqueue_worker( int $offset, bool $unique = false ): void {
    PvProductSyncWorker::enqueue_worker( $offset, $unique );
  }
}
