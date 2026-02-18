<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvBexioReferenceData
 *
 * Fetches and caches Bexio reference lists that are needed for order creation:
 *   - Sales taxes  (GET 2.0/taxes?types=sales_tax&scope=active)
 *   - Bexio users  (GET 3.0/users)
 *
 * The cached values are stored as WP options (JSON strings).
 * On each refresh, the new data is compared to the stored data.
 * If it differs, a "stale" flag is set so the admin UI can warn the user
 * to re-check their tax mapping / default user selection.
 *
 * Refresh is scheduled once per day via Action Scheduler.
 * It can also be triggered on-demand via the REST endpoint
 * POST /pingvin/v1/refreshReferenceData.
 *
 * Option keys
 * -----------
 * pv_bexio_taxes_cache   — JSON array of tax objects from Bexio
 * pv_bexio_users_cache   — JSON array of user objects from Bexio
 * pv_bexio_taxes_stale   — '1' when the taxes list changed since last user save
 * pv_bexio_users_stale   — '1' when the users list changed since last user save
 */
class PvBexioReferenceData {

  const AS_HOOK    = 'pv_refresh_bexio_reference_data';
  const INTERVAL   = DAY_IN_SECONDS;
  const LOG_PREFIX = '[ReferenceData]';

  /** @var bool Prevents duplicate hook registration across instances. */
  private static bool $hooked = false;

  public function __construct() {
    if ( ! self::$hooked ) {
      add_action( self::AS_HOOK, [ $this, 'refresh' ] );
      self::$hooked = true;
    }

    // Defer scheduling to 'init' so Action Scheduler functions are available.
    add_action( 'init', [ $this, 'maybe_schedule' ] );
  }

  /**
   * Schedules the daily recurring action if it is not already queued.
   * Called on the 'init' hook so AS functions are definitely loaded.
   */
  public function maybe_schedule(): void {
    if ( ! \as_has_scheduled_action( self::AS_HOOK ) ) {
      \as_schedule_recurring_action(
        time() + self::INTERVAL,
        self::INTERVAL,
        self::AS_HOOK,
        [],
        'pv_sync',
        true
      );
      PingvinLogger::log( 'info', self::LOG_PREFIX . ' Daily refresh scheduled.' );
    }
  }

  /**
   * Returns true when the daily recurring action is queued in Action Scheduler.
   */
  public static function is_scheduled(): bool {
    return (bool) \as_has_scheduled_action( self::AS_HOOK );
  }

  // ---------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------

  /**
   * Fetches fresh data from Bexio, updates the cache, and sets stale flags
   * if the data has changed since the last save.
   *
   * Called by Action Scheduler daily, and on-demand from the REST endpoint.
   *
   * @return array{taxes: bool, users: bool}  True for each type that was updated.
   */
  public function refresh(): array {
    $updated = [
      'taxes' => $this->refresh_taxes(),
      'users' => $this->refresh_users(),
    ];

    PingvinLogger::log(
      'info',
      self::LOG_PREFIX . ' Reference data refresh complete.'
      . ' taxes_updated=' . ( $updated['taxes'] ? 'yes' : 'no' )
      . ' users_updated=' . ( $updated['users'] ? 'yes' : 'no' )
    );

    return $updated;
  }

  /**
   * Returns the cached taxes array, or null if the cache is empty.
   *
   * @return array|null
   */
  public static function get_taxes(): ?array {
    $raw = get_option( 'pv_bexio_taxes_cache' );
    return $raw ? json_decode( $raw, true ) : null;
  }

  /**
   * Returns the cached users array, or null if the cache is empty.
   *
   * @return array|null
   */
  public static function get_users(): ?array {
    $raw = get_option( 'pv_bexio_users_cache' );
    return $raw ? json_decode( $raw, true ) : null;
  }

  /**
   * Clears the stale flag for taxes (call after user has reviewed + saved mapping).
   */
  public static function clear_taxes_stale(): void {
    delete_option( 'pv_bexio_taxes_stale' );
  }

  /**
   * Clears the stale flag for users (call after user has reviewed + saved selection).
   */
  public static function clear_users_stale(): void {
    delete_option( 'pv_bexio_users_stale' );
  }

  // ---------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------

  /**
   * Fetches sales taxes from Bexio and updates the cache.
   *
   * @return bool  True if the data changed.
   */
  private function refresh_taxes(): bool {
    $res = pv_api_call( 'GET', '3.0/taxes?types=sales_tax&scope=active' );

    if ( empty( $res ) || (int) ( $res['status'] ?? 0 ) !== 200 ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . ' Failed to fetch taxes from Bexio (HTTP ' . ( $res['status'] ?? '?' ) . ').'
      );
      return false;
    }

    $items = $res['result'];
    if ( ! is_array( $items ) ) {
      PingvinLogger::log( 'error', self::LOG_PREFIX . ' Unexpected taxes response format.' );
      return false;
    }

    // Normalise to plain arrays for consistent JSON encoding.
    $normalised = json_encode( $items );
    $stored     = get_option( 'pv_bexio_taxes_cache', '' );

    if ( $normalised === $stored ) {
      return false; // Nothing changed.
    }

    update_option( 'pv_bexio_taxes_cache', $normalised );

    // Only flag stale if there was already a stored value (i.e. first-run is not stale).
    if ( $stored !== '' && $stored !== false ) {
      update_option( 'pv_bexio_taxes_stale', '1' );
      PingvinLogger::log( 'warning', self::LOG_PREFIX . ' Bexio tax list changed — stale flag set.' );
    }

    return true;
  }

  /**
   * Fetches users from Bexio and updates the cache.
   *
   * @return bool  True if the data changed.
   */
  private function refresh_users(): bool {
    $res = pv_api_call( 'GET', '3.0/users' );

    if ( empty( $res ) || (int) ( $res['status'] ?? 0 ) !== 200 ) {
      PingvinLogger::log(
        'error',
        self::LOG_PREFIX . ' Failed to fetch users from Bexio (HTTP ' . ( $res['status'] ?? '?' ) . ').'
      );
      return false;
    }

    $items = $res['result'];
    if ( ! is_array( $items ) ) {
      PingvinLogger::log( 'error', self::LOG_PREFIX . ' Unexpected users response format.' );
      return false;
    }

    $normalised = json_encode( $items );
    $stored     = get_option( 'pv_bexio_users_cache', '' );

    if ( $normalised === $stored ) {
      return false;
    }

    update_option( 'pv_bexio_users_cache', $normalised );

    if ( $stored !== '' && $stored !== false ) {
      update_option( 'pv_bexio_users_stale', '1' );
      PingvinLogger::log( 'warning', self::LOG_PREFIX . ' Bexio user list changed — stale flag set.' );
    }

    return true;
  }
}
