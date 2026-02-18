<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvContactSyncEngine
 *
 * Concrete engine for the Bexio → WooCommerce contact sync.
 * All coordinator / watchdog / state-machine logic lives in PvAbstractSyncEngine.
 * This class only supplies the six required constants and the two abstract
 * worker-coupling methods.
 */
class PvContactSyncEngine extends PvAbstractSyncEngine {

  const STATE_KEY        = 'pv_sync_state_contacts';
  const COORDINATOR_HOOK = 'pv_sync_coordinator_contacts';
  const WORKER_HOOK      = 'pv_sync_worker_contacts';
  const SETTINGS_KEY     = 'pv_bexio_contactsync_action_settings';
  const NEXT_TRANSIENT   = 'pv_bexio_contact_next';
  const LOG_PREFIX       = '[ContactEngine]';

  protected function enqueue_first_worker(): void {
    PvContactSyncWorker::enqueue_worker( 0 );
  }

  protected function enqueue_worker( int $offset, bool $unique = false ): void {
    PvContactSyncWorker::enqueue_worker( $offset, $unique );
  }
}
