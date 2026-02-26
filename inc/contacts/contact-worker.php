<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * PvContactSyncWorker
 *
 * Handles one batch of BATCH_SIZE contacts per Action Scheduler run.
 * Mirrors PvProductSyncWorker. Syncs Bexio contacts → WordPress users
 * with WooCommerce customer billing meta.
 *
 * Bexio endpoint: GET 2.0/contact?offset={n}&limit={BATCH_SIZE}
 * WP link meta:   _pvbexio_contact_id  (stored on the WP user)
 * Match strategy: 1. user meta _bexio_contact_id  (persistent link)
 *                 2. user_email match             (first-time link)
 *                 3. create new WP user           (never seen before)
 *
 * Contacts without a `mail` field are skipped — a WP user account
 * requires an e-mail address.
 */
class PvContactSyncWorker {

  /** Mirrors the constant in PvContactSyncEngine for convenience. */
  const HOOK = 'pvbexio_sync_worker_contacts';

  /**
   * Contacts are lighter than products — use a larger batch so each
   * Action Scheduler action covers more ground without hitting memory
   * or time limits.
   */
  const BATCH_SIZE = 10;

  /** Prevents registering add_action more than once across all instances. */
  private static bool $hooked = false;

  public function __construct() {
    if ( ! self::$hooked ) {
      add_action( self::HOOK, [ $this, 'run' ], 10, 1 );
      self::$hooked = true;
    }
  }

  /**
   * @param int $offset  Zero-based contact offset within the full Bexio list.
   */
  public function run( int $offset ): void {
    PingvinLogger::log( 'info', "[ContactWorker] Batch start — offset: $offset." );

    $engine = new PvContactSyncEngine();
    $state  = $engine->get_state();

    // Safety: abort if the cycle was cancelled between enqueueing and running.
    if ( $state['status'] !== 'running' ) {
      PingvinLogger::log(
        'info',
        "[ContactWorker] Cycle no longer running (status: {$state['status']}). Aborting at offset $offset."
      );
      return;
    }

    // Heartbeat: record worker is alive and advance next_offset immediately
    // so the watchdog cannot re-enqueue the same offset while we are running.
    $engine->set_state( [
      'last_worker_at' => time(),
      'next_offset'    => $offset + self::BATCH_SIZE,
    ] );

    // ---------------------------------------------------------------
    // 1. Fetch batch from Bexio
    // ---------------------------------------------------------------
    $contacts = $this->fetch_bexio_batch( $offset );

    if ( $contacts === null ) {
      $error_msg = "[ContactWorker] Bexio API call failed at offset $offset. AS will retry.";
      $engine->set_state( [ 'last_error' => $error_msg ] );
      throw new \RuntimeException( esc_html( $error_msg ) );
    }

    $count = count( $contacts );
    PingvinLogger::log( 'info', "[ContactWorker] Fetched $count contacts from Bexio (offset: $offset)." );

    // ---------------------------------------------------------------
    // 2. Upsert each contact as a WP user
    // ---------------------------------------------------------------
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $failed  = 0;

    foreach ( $contacts as $bexio_contact ) {
      try {
        $result = $this->upsert_contact( $bexio_contact );
        switch ( $result ) {
          case 'created': $created++; break;
          case 'updated': $updated++; break;
          case 'skipped': $skipped++; break;
          default:        $failed++;  break;
        }
      } catch ( \Throwable $e ) {
        $failed++;
        PingvinLogger::log(
          'error',
          "[ContactWorker] Exception processing contact #{$bexio_contact->id}: " . $e->getMessage()
        );
      }
    }

    PingvinLogger::log(
      'info',
      "[ContactWorker] Batch complete — created: $created, updated: $updated, skipped: $skipped, failed: $failed."
    );

    // ---------------------------------------------------------------
    // 3. Update state + decide whether to continue
    // ---------------------------------------------------------------
    $this->finish_batch( $engine, $offset, $count, $created + $updated, $skipped, $failed );
  }

  /**
   * Updates sync state and enqueues the next worker if the batch was full.
   *
   * @param PvContactSyncEngine $engine
   * @param int                 $offset   The offset this batch started at.
   * @param int                 $fetched  Items returned by Bexio (drives last-batch detection).
   * @param int                 $upserted Items actually saved (created + updated).
   * @param int                 $skipped  Items skipped (no email / data unchanged).
   * @param int                 $failed   Items that failed to save.
   */
  private function finish_batch(
    PvContactSyncEngine $engine,
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

    $is_last_batch = ( $fetched < self::BATCH_SIZE );

    if ( $is_last_batch ) {
      PingvinLogger::log(
        'info',
        "[ContactWorker] Last batch reached (fetched: $fetched). Cycle complete — "
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
      PingvinLogger::log(
        'info',
        "[ContactWorker] Batch done (fetched: $fetched, upserted: $upserted, skipped: $skipped, failed: $failed). Enqueueing next at offset $next."
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

  /**
   * Fetches one page of contacts from Bexio.
   *
   * Returns an array of stdClass objects, or null on API failure.
   *
   * @param int $offset
   * @return array|null
   */
  private function fetch_bexio_batch( int $offset ): ?array {
    $endpoint = '2.0/contact?offset=' . $offset . '&limit=' . self::BATCH_SIZE;
    $res      = pvbexio_api_call( 'GET', $endpoint );

    if ( empty( $res ) || ! isset( $res['status'] ) ) {
      PingvinLogger::log( 'error', "[ContactWorker] No response from Bexio API (offset: $offset)." );
      return null;
    }

    if ( (int) $res['status'] !== 200 ) {
      PingvinLogger::log(
        'error',
        "[ContactWorker] Bexio API returned HTTP {$res['status']} (offset: $offset)."
      );
      return null;
    }

    $items = $res['result'];

    if ( ! is_array( $items ) ) {
      PingvinLogger::log( 'error', "[ContactWorker] Unexpected Bexio response format (offset: $offset)." );
      return null;
    }

    return $items;
  }

  /**
   * Creates or updates a single WordPress user from a Bexio contact.
   *
   * Match strategy (in order):
   *   1. WP user with user meta `_bexio_contact_id` = $c->id  (persistent link)
   *   2. WP user whose user_email matches $c->mail            (first-time link)
   *      → if that user is already linked to a DIFFERENT Bexio contact: skip (conflict)
   *   3. No match → create a new WP user
   *
   * Name mapping:
   *   contact_type_id 1 (company): name_1 = company name, name_2 = company addition
   *   contact_type_id 2 (person):  name_1 = last name,    name_2 = first name
   *
   * Address: street_name + house_number → billing/shipping address_1;
   *          address_addition → address_2. Country deferred (no ISO code from Bexio).
   *
   * @param object $c  Raw Bexio contact object.
   * @return string  'created' | 'updated' | 'skipped' | 'failed'
   */
  private function upsert_contact( object $c ): string {
    $email = isset( $c->mail ) ? trim( (string) $c->mail ) : '';

    // Skip contacts without an e-mail address.
    if ( $email === '' ) {
      PingvinLogger::log(
        'info',
        "[ContactWorker] Skipping contact #{$c->id} ({$c->name_1}) — no email address."
      );
      return 'skipped';
    }

    // contact_type_id 1 = company, 2 = person.
    $is_company   = ( (int) ( $c->contact_type_id ?? 2 ) === 1 );
    $first_name   = '';
    $last_name    = '';
    $company_name = '';

    if ( $is_company ) {
      $company_name = trim( (string) ( $c->name_1 ?? '' ) );
      // name_2 = company addition (department/unit) — not a person's given name.
    } else {
      $first_name = trim( (string) ( $c->name_2 ?? '' ) ); // given name
      $last_name  = trim( (string) ( $c->name_1 ?? '' ) ); // family name
    }

    $display_name = $is_company
      ? $company_name
      : ( trim( "$first_name $last_name" ) ?: $email );

    // Prefer the split fields; fall back to the pre-combined `address` field.
    $street    = trim( (string) ( $c->street_name  ?? '' ) );
    $house_nr  = trim( (string) ( $c->house_number ?? '' ) );
    $address_1 = $street !== ''
      ? rtrim( "$street $house_nr" )
      : trim( (string) ( $c->address ?? '' ) );
    $address_2 = trim( (string) ( $c->address_addition ?? '' ) );
    $city      = trim( (string) ( $c->city     ?? '' ) );
    $postcode  = trim( (string) ( $c->postcode ?? '' ) );
    // country_id → ISO 2-letter code: deferred — requires Bexio /countries endpoint.

    $phone = trim( (string) ( $c->phone_mobile ?? '' ) );
    if ( $phone === '' ) {
      $phone = trim( (string) ( $c->phone_fixed ?? '' ) );
    }

    // Hash only the fields we actually sync, NOT the full raw object.
    // Bexio responses contain volatile fields (e.g. updated_at) that differ
    // between API calls even when no user-visible data changed — hashing the
    // whole object caused a skip→update oscillation every other cycle.
    $hash = md5( wp_json_encode( [
      'id'              => (int) $c->id,
      'nr'              => $c->nr ?? null,
      'contact_type_id' => $c->contact_type_id ?? null,
      'email'           => $email,
      'first_name'      => $first_name,
      'last_name'       => $last_name,
      'company_name'    => $company_name,
      'address_1'       => $address_1,
      'address_2'       => $address_2,
      'city'            => $city,
      'postcode'        => $postcode,
      'phone'           => $phone,
    ] ) );

    $wp_user_id = null;
    $is_new     = false;

    $by_meta = get_users( [
      'meta_key'   => '_pvbexio_contact_id',
      'meta_value' => (int) $c->id,
      'number'     => 1,
      'fields'     => 'ID',
    ] );
    if ( ! empty( $by_meta ) ) {
      $wp_user_id = (int) $by_meta[0];
    }

    if ( $wp_user_id === null ) {
      $by_email = get_user_by( 'email', $email );
      if ( $by_email ) {
        $existing_bexio_id = get_user_meta( (int) $by_email->ID, '_pvbexio_contact_id', true );

        if ( $existing_bexio_id !== '' && (int) $existing_bexio_id !== (int) $c->id ) {
          // This email already belongs to a WP user linked to a DIFFERENT Bexio contact.
          // Creating a new user would violate WP's unique-email constraint anyway.
          // Skip and surface the conflict so it can be resolved manually.
          PingvinLogger::log(
            'warning',
            "[ContactWorker] Email conflict for Bexio contact #{$c->id} ({$email}): "
            . "WP user {$by_email->ID} is already linked to Bexio contact #{$existing_bexio_id}. Skipping."
          );
          return 'skipped';
        }

        // Unlinked WP user with the same email — link and update rather than
        // attempting (and failing) to create a duplicate.
        $wp_user_id = (int) $by_email->ID;
      }
    }

    if ( $wp_user_id !== null && get_user_meta( $wp_user_id, '_pvbexio_hash', true ) === $hash ) {
      return 'skipped';
    }

    $user_data = [
      'user_email'   => $email,
      'first_name'   => $first_name,
      'last_name'    => $last_name,
      'display_name' => $display_name
    ];

    if ( $wp_user_id !== null ) {
      // Security: never let the sync overwrite an administrator, editor, or any
      // other privileged account that could exist with the same email address.
      // Only customer / subscriber level accounts are safe to update automatically.
      $existing = get_userdata( $wp_user_id );
      if ( $existing && ( user_can( $existing, 'manage_options' ) || user_can( $existing, 'edit_posts' ) ) ) {
        PingvinLogger::log(
          'warning',
          "[ContactWorker] Skipping update for privileged user #{$wp_user_id} ({$email}) — Bexio contact #{$c->id}."
        );
        return 'skipped';
      }

      $user_data['ID'] = $wp_user_id;
      $result = wp_update_user( $user_data );
    } else {
      $is_new  = true;
      $login   = sanitize_user( strstr( $email, '@', true ), true );
      if ( username_exists( $login ) ) {
        $login = $login . '_bx' . $c->id;
      }
      $user_data['user_login'] = $login;
      $user_data['user_pass']  = wp_generate_password( 24, true, true );
      // Explicitly set role to 'customer' regardless of the site's default user role
      // setting, so this sync can never accidentally create a privileged account.
      $user_data['role'] = 'customer';
      $result = wp_insert_user( $user_data );
    }

    if ( is_wp_error( $result ) ) {
      PingvinLogger::log(
        'error',
        '[ContactWorker] Failed to ' . ( $is_new ? 'create' : 'update' )
        . " WP user for Bexio contact #{$c->id} ({$email}): " . $result->get_error_message()
      );
      return 'failed';
    }

    $wp_user_id = (int) $result;

    update_user_meta( $wp_user_id, 'billing_first_name', $first_name );
    update_user_meta( $wp_user_id, 'billing_last_name',  $last_name );
    update_user_meta( $wp_user_id, 'billing_company',    $company_name );
    update_user_meta( $wp_user_id, 'billing_address_1',  $address_1 );
    update_user_meta( $wp_user_id, 'billing_address_2',  $address_2 );
    update_user_meta( $wp_user_id, 'billing_city',       $city );
    update_user_meta( $wp_user_id, 'billing_postcode',   $postcode );
    update_user_meta( $wp_user_id, 'billing_email',      $email );
    update_user_meta( $wp_user_id, 'billing_phone',      $phone );
    // billing_country: deferred — country_id needs mapping to ISO 2-letter code.

    update_user_meta( $wp_user_id, 'shipping_first_name', $first_name );
    update_user_meta( $wp_user_id, 'shipping_last_name',  $last_name );
    update_user_meta( $wp_user_id, 'shipping_company',    $company_name );
    update_user_meta( $wp_user_id, 'shipping_address_1',  $address_1 );
    update_user_meta( $wp_user_id, 'shipping_address_2',  $address_2 );
    update_user_meta( $wp_user_id, 'shipping_city',       $city );
    update_user_meta( $wp_user_id, 'shipping_postcode',   $postcode );
    // shipping_country: deferred — same reason.

    update_user_meta( $wp_user_id, '_pvbexio_hash',       $hash );
    update_user_meta( $wp_user_id, '_pvbexio_contact_id', (int) $c->id );
    update_user_meta( $wp_user_id, '_pvbexio_contact_nr', $c->nr ?? null );  // Bexio contact number
    update_user_meta( $wp_user_id, '_pvbexio_data', wp_json_encode( [
      'last_sync' => current_time( 'Y-m-d H:i:s' ),
      'data'      => $c,
    ] ) );

    PingvinLogger::log(
      'info',
      '[ContactWorker] ' . ( $is_new ? 'Created' : 'Updated' )
      . " WP user (id: $wp_user_id / email: $email / bexio_id: {$c->id})."
    );

    return $is_new ? 'created' : 'updated';
  }

  /**
   * Enqueues a worker action for the given offset.
   *
   * Deliberately does NOT use unique=true (same reasoning as the product
   * worker — see that class for full explanation). Pass $unique = true
   * only from the coordinator watchdog recovery path.
   *
   * @param int  $offset
   * @param bool $unique  Pass true only from the coordinator watchdog.
   */
  public static function enqueue_worker( int $offset, bool $unique = false ): void {
    $action_id = as_schedule_single_action(
      time(),
      self::HOOK,
      [ 'offset' => $offset ],
      'pvbexio_sync',
      $unique
    );

    if ( ! $action_id ) {
      PingvinLogger::log(
        'error',
        "[ContactWorker] as_schedule_single_action returned 0 for offset $offset "
        . '(unique=' . ( $unique ? 'true' : 'false' ) . '). '
        . 'The coordinator watchdog will re-enqueue on the next tick.'
      );
      return;
    }

    PingvinLogger::log( 'info', "[ContactWorker] Worker enqueued (id: $action_id, offset: $offset)." );
  }
}
