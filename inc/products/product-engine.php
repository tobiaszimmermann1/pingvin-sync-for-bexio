<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvProductSyncEngine
 *
 * Concrete engine for the Bexio → WooCommerce product sync.
 */
class PvProductSyncEngine extends PvAbstractSyncEngine {

  const STATE_KEY        = 'pvbexio_sync_state_products';
  const COORDINATOR_HOOK = 'pvbexio_sync_coordinator';
  const WORKER_HOOK      = 'pvbexio_sync_worker_products';
  const SETTINGS_KEY     = 'pvbexio_productsync_action_settings';
  const NEXT_TRANSIENT   = 'pvbexio_connector_next';
  const LOG_PREFIX       = '[ProductEngine]';

  protected function enqueue_first_worker(): void {
    PvProductSyncWorker::enqueue_worker( 0 );
  }

  protected function enqueue_worker( int $offset, bool $unique = false ): void {
    PvProductSyncWorker::enqueue_worker( $offset, $unique );
  }
}
