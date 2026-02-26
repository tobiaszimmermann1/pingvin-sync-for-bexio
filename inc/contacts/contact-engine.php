<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvContactSyncEngine
 *
 * Concrete engine for the Bexio → WooCommerce contact sync.
 */
class PvContactSyncEngine extends PvAbstractSyncEngine {

  const STATE_KEY        = 'pvbexio_sync_state_contacts';
  const COORDINATOR_HOOK = 'pvbexio_sync_coordinator_contacts';
  const WORKER_HOOK      = 'pvbexio_sync_worker_contacts';
  const SETTINGS_KEY     = 'pvbexio_contactsync_action_settings';
  const NEXT_TRANSIENT   = 'pvbexio_contact_next';
  const LOG_PREFIX       = '[ContactEngine]';

  protected function enqueue_first_worker(): void {
    PvContactSyncWorker::enqueue_worker( 0 );
  }

  protected function enqueue_worker( int $offset, bool $unique = false ): void {
    PvContactSyncWorker::enqueue_worker( $offset, $unique );
  }
}
