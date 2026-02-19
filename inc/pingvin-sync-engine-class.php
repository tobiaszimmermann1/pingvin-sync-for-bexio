<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvAbstractSyncEngine
 *
 * Shared coordinator / watchdog / state-machine logic for all Bexio sync types.
 * Concrete subclasses supply the six constants and two abstract methods below.
 *
 * Required constants (define in every subclass):
 *   const STATE_KEY        — WP option key for the state JSON
 *   const COORDINATOR_HOOK — AS hook name for the recurring coordinator
 *   const WORKER_HOOK      — AS hook name for single-action workers
 *   const SETTINGS_KEY     — WP option key for {enabled, interval} settings JSON
 *   const NEXT_TRANSIENT   — WP transient key for "next run" UI display
 *   const LOG_PREFIX       — Log prefix string, e.g. '[ProductEngine]'
 *
 * Required abstract methods (implement in every subclass):
 *   enqueue_first_worker(): void        — enqueue offset-0 worker at cycle start
 *   enqueue_worker( int, bool ): void   — enqueue any offset (watchdog recovery)
 */
abstract class PvAbstractSyncEngine {

  /** Seconds after which any "running" cycle is considered globally stale. */
  const MAX_STALE_SECONDS = 1800; // 30 minutes

  /**
   * Per-class hook guard keyed by concrete class name so each engine subclass
   * registers its own hooks independently.
   *
   * @var bool[]
   */
  private static array $hooked = [];

  public function __construct() {
    $class = static::class;
    if ( isset( self::$hooked[ $class ] ) ) {
      return;
    }

    add_action(
      'update_option_' . static::SETTINGS_KEY,
      [ $this, 'on_settings_updated' ],
      10, 2
    );

    add_action( static::COORDINATOR_HOOK, [ $this, 'run_coordinator' ] );

    self::$hooked[ $class ] = true;
  }

  /**
   * Enqueue the first worker (offset 0) for this sync type.
   * Called at the start of each new cycle.
   */
  abstract protected function enqueue_first_worker(): void;

  /**
   * Enqueue a worker at an arbitrary offset.
   * Called by the watchdog to recover a broken chain.
   *
   * @param int  $offset
   * @param bool $unique  Pass true only from the watchdog.
   */
  abstract protected function enqueue_worker( int $offset, bool $unique = false ): void;

  /**
   * Fires when the engine's SETTINGS_KEY option is updated.
   * Schedules or unschedules the coordinator recurring action.
   */
  public function on_settings_updated( $old_value, $new_value ): void {
    if ( $old_value === $new_value ) {
      return;
    }

    $options = json_decode( $new_value );
    if ( $options === null ) {
      PingvinLogger::log( 'error', static::LOG_PREFIX . ' Could not decode updated settings JSON.' );
      return;
    }

    $sync_enabled = ! empty( $options->enabled );
    $interval     = isset( $options->interval ) ? (int) $options->interval : 900;

    if ( $sync_enabled ) {
      PingvinLogger::log( 'info', static::LOG_PREFIX . " Sync enabled (interval: {$interval}s)." );
      $this->schedule_coordinator( $interval );
    } else {
      PingvinLogger::log( 'info', static::LOG_PREFIX . ' Sync disabled.' );
      $this->unschedule_coordinator();
    }
  }

  /**
   * (Re-)schedules the coordinator at the given interval.
   * Always clears any previous coordinator action first.
   */
  private function schedule_coordinator( int $interval ): void {
    if ( as_has_scheduled_action( static::COORDINATOR_HOOK ) ) {
      as_unschedule_all_actions( static::COORDINATOR_HOOK );
      PingvinLogger::log( 'info', static::LOG_PREFIX . ' Previous coordinator unscheduled.' );
    }

    // First run deferred by one interval so a settings save does not
    // immediately trigger a full sync.
    $first_run = time() + $interval;
    $action_id = as_schedule_recurring_action(
      $first_run,
      $interval,
      static::COORDINATOR_HOOK,
      [],
      '',
      true,
      1
    );

    PingvinLogger::log(
      'info',
      static::LOG_PREFIX . " Coordinator scheduled (id: $action_id, interval: {$interval}s)."
    );

    $this->update_next_transient( $interval );
  }

  /**
   * Unschedules the coordinator, cancels pending workers, clears state.
   */
  private function unschedule_coordinator(): void {
    if ( as_has_scheduled_action( static::COORDINATOR_HOOK ) ) {
      as_unschedule_all_actions( static::COORDINATOR_HOOK );
      PingvinLogger::log( 'info', static::LOG_PREFIX . ' Coordinator unscheduled (sync disabled).' );
    }

    as_unschedule_all_actions( static::WORKER_HOOK );

    delete_transient( static::NEXT_TRANSIENT );
    $this->reset_state();
  }

  /**
   * Main coordinator callback — runs on every tick of the recurring action.
   *
   * 1. If a cycle is running and globally stale → mark error, fall through to restart.
   * 2. If running and healthy → run worker-chain watchdog.
   * 3. If idle / error → start a new cycle.
   */
  public function run_coordinator(): void {
    PingvinLogger::log( 'info', static::LOG_PREFIX . ' Coordinator tick.' );

    // Guard: bail immediately if sync has been disabled since the coordinator
    // was scheduled. This covers the race window where AS fires a tick after
    // the user toggled the option off (before unschedule_coordinator took effect).
    $settings = json_decode( get_option( static::SETTINGS_KEY, '{}' ), true );
    if ( empty( $settings['enabled'] ) ) {
      PingvinLogger::log( 'info', static::LOG_PREFIX . ' Sync is disabled — skipping tick and unscheduling coordinator.' );
      $this->unschedule_coordinator();
      return;
    }

    $state = $this->get_state();

    if ( $state['status'] === 'running' ) {
      $age = time() - (int) $state['started_at'];

      if ( $age >= self::MAX_STALE_SECONDS ) {
        PingvinLogger::log(
          'warning',
          static::LOG_PREFIX . " Stale cycle detected (age: {$age}s). Marking as error and starting fresh."
        );
        $this->set_state( [
          'status'     => 'error',
          'last_error' => "Stale cycle detected after {$age}s.",
        ] );
      } else {
        $worker_age         = time() - (int) $state['last_worker_at'];
        $tick_interval      = isset( $settings['interval'] ) ? (int) $settings['interval'] : 900;
        $watchdog_threshold = $tick_interval * 3;

        if ( $worker_age > $watchdog_threshold && $state['next_offset'] !== null ) {
          PingvinLogger::log(
            'warning',
            static::LOG_PREFIX . " Chain watchdog triggered (last worker: {$worker_age}s ago, threshold: {$watchdog_threshold}s = 3×{$tick_interval}s). Re-enqueueing offset {$state['next_offset']}."
          );
          $this->enqueue_worker( (int) $state['next_offset'], true );
        } else {
          PingvinLogger::log(
            'info',
            static::LOG_PREFIX . " Cycle running, chain healthy (last worker: {$worker_age}s ago). Skipping tick."
          );
        }
        return;
      }
    }

    as_unschedule_all_actions( static::WORKER_HOOK );

    PingvinLogger::log( 'info', static::LOG_PREFIX . ' Starting new sync cycle.' );

    $this->set_state( [
      'status'            => 'running',
      'started_at'        => time(),
      'next_offset'       => 0,
      'total_processed'   => 0,
      'skipped_count'     => 0,
      'failed_count'      => 0,
      'completed_batches' => 0,
      'last_worker_at'    => time(),
      'last_error'        => null,
    ] );

    $this->enqueue_first_worker();
    PingvinLogger::log( 'info', static::LOG_PREFIX . ' First worker enqueued. Coordinator done for this tick.' );
  }

  /**
   * Returns the current sync state array.
   * If no state exists yet, returns the default idle state.
   */
  public function get_state(): array {
    $raw = get_option( static::STATE_KEY, null );
    if ( $raw === null || $raw === false ) {
      return $this->default_state();
    }
    $decoded = json_decode( $raw, true );
    return is_array( $decoded ) ? $decoded : $this->default_state();
  }

  /**
   * Merges the provided fields into the current state and persists it.
   *
   * @param array $fields  Partial state fields to update.
   */
  public function set_state( array $fields ): void {
    $current = $this->get_state();
    $updated = array_merge( $current, $fields );
    update_option( static::STATE_KEY, wp_json_encode( $updated ), false );
  }

  /**
   * Resets state to the default idle state.
   */
  public function reset_state(): void {
    update_option( static::STATE_KEY, wp_json_encode( $this->default_state() ), false );
  }

  /**
   * The canonical "idle" state shape.
   * All fields are always present so consumers never need to null-check.
   */
  private function default_state(): array {
    return [
      'status'            => 'idle',
      'started_at'        => 0,
      'next_offset'       => null,  // offset the next needed worker should start at
      'total_processed'   => 0,     // records created or updated this cycle
      'skipped_count'     => 0,     // records skipped (Bexio data unchanged)
      'failed_count'      => 0,     // records that failed to save
      'completed_batches' => 0,
      'last_worker_at'    => 0,     // timestamp of last worker heartbeat
      'last_completed_at' => 0,
      'last_error'        => null,
    ];
  }

  /**
   * Updates the NEXT_TRANSIENT used by the UI to show the next scheduled run.
   */
  private function update_next_transient( int $interval ): void {
    try {
      $formatter = new \IntlDateFormatter(
        'de_DE',
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::SHORT
      );
      $next = ( new \DateTime() )->modify( "+{$interval} seconds" );
      set_transient(
        static::NEXT_TRANSIENT,
        wp_json_encode( [ 'date' => $formatter->format( $next ) ] ),
        0
      );
    } catch ( \Exception $e ) {
      PingvinLogger::log( 'error', static::LOG_PREFIX . ' Could not update next-sync transient: ' . $e->getMessage() );
    }
  }
}
