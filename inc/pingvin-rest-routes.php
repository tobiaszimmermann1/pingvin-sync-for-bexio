<?php 
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// import API calling helper function
require_once 'pingvin-call.php';

class PvBexioRestRoutes {

  function __construct() {
    add_action( 'rest_api_init', array($this, 'rest_routes'));
  }

  function rest_routes() {
    /*************************************
     * Bexio specific routes
     *////////////////////////////////////

    // get products 
    register_rest_route( 'pingvin/v1', '/article', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_articles'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));

    /*************************************
     * Plugin specific routes
     *////////////////////////////////////
    
    // get sync settings
    register_rest_route( 'pingvin/v1', '/syncSettings', array(
      'methods' => 'GET',
      'callback' => array($this, 'sync_settings'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // get transient
    register_rest_route( 'pingvin/v1', '/transient', array(
      'methods' => 'GET',
      'callback' => array($this, 'transient'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));

    // get product sync state
    register_rest_route( 'pingvin/v1', '/syncState', array(
      'methods' => 'GET',
      'callback' => array($this, 'sync_state'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // save sync settings
    register_rest_route( 'pingvin/v1', '/syncSettings', array(
      'methods' => 'POST',
      'callback' => array($this, 'sync_settings'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // get users
    register_rest_route( 'pingvin/v1', '/users', array(
      'methods' => 'GET',
      'callback' => array($this, 'users'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // get products
    register_rest_route( 'pingvin/v1', '/products', array(
      'methods' => 'GET',
      'callback' => array($this, 'products'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // get orders
    register_rest_route( 'pingvin/v1', '/orders', array(
      'methods' => 'GET',
      'callback' => array($this, 'orders'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));

    // get log files / log lines
    register_rest_route( 'pingvin/v1', '/logs', array(
      'methods' => 'GET',
      'callback' => array($this, 'logs'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));

  }

  /*************************************
   * Callback functions
   *////////////////////////////////////

  function get_articles($request) {
    $result = pv_api_call('GET', '2.0/article');
    return $result;
  }

  function sync_settings($request) {
    $type_map = [
      'products' => 'pv_bexio_productsync_action_settings',
      'contacts' => 'pv_bexio_contactsync_action_settings',
    ];

    $type = $request->get_param('type') ?? 'products';

    if ( ! isset( $type_map[ $type ] ) ) {
      return new \WP_REST_Response( [ 'message' => 'Invalid type. Use products or contacts.' ], 400 );
    }

    $option_key = $type_map[ $type ];

    if ($request->get_method() === "GET") {
      $raw = get_option( $option_key );
      if ( ! $raw ) return new \WP_REST_Response( null, 200 );
      return new \WP_REST_Response( json_decode( $raw, true ), 200 );
    }

    if ($request->get_method() === "POST") {
      $params   = $request->get_json_params();
      $allowed  = [ 60, 120, 300, 3600, 14400, 86400 ];
      $interval = (int) ( $params['interval'] ?? 0 );

      $sanitized = [
        'enabled'  => ! empty( $params['enabled'] ),
        'interval' => in_array( $interval, $allowed, true ) ? $interval : 900,
      ];

      update_option( $option_key, json_encode( $sanitized ) );

      return new \WP_REST_Response( [ 'message' => 'Settings saved', 'data' => $sanitized ], 200 );
    }

    return new \WP_REST_Response( [ 'message' => 'Method not allowed' ], 405 );
  }

  function transient() {
    $transient_status = get_transient('pv_bexio_connector_status');
    $transient_next = get_transient('pv_bexio_connector_next');

    return array(
      'status' => $transient_status,
      'next' => $transient_next
    );
  }

  function sync_state( $request ) {
    $type_map = [
      'products' => 'pv_sync_state_products',
      'contacts' => 'pv_sync_state_contacts',
    ];

    $type = $request->get_param( 'type' ) ?? 'products';

    if ( ! isset( $type_map[ $type ] ) ) {
      return new \WP_REST_Response( [ 'message' => 'Invalid type. Use products or contacts.' ], 400 );
    }

    $raw = get_option( $type_map[ $type ], null );
    if ( ! $raw ) {
      return new \WP_REST_Response( null, 200 );
    }
    $state = json_decode( $raw, true );

    // Format timestamps for display so the frontend doesn't need to do it.
    $fmt = function( $ts ) {
      if ( ! $ts ) return null;
      return wp_date( 'd.m.Y - H:i', (int) $ts );
    };

    $state['last_completed_at_fmt'] = $fmt( $state['last_completed_at'] ?? 0 );
    $state['started_at_fmt']        = $fmt( $state['started_at']        ?? 0 );

    return new \WP_REST_Response( $state, 200 );
  }


  function users($request) {
    if ($request->get_method() === "GET") {
      $offset = intval($request->get_param('offset'));
      if ($offset < 0) $offset = 0;

      $page_size = intval($request->get_param('page_size'));
      if ($page_size <= 0) $page_size = 50; 
      $max_page_size = 200;
      if ($page_size > $max_page_size) $page_size = $max_page_size;

      $args = array(
        'number' => $page_size,
        'offset' => $offset,
      );

      $query = new \WP_User_Query($args);
      $users = $query->get_results();
      $total = $query->get_total();

      // build items array and include the `pv_bexio_sync` user meta
      $items = array();
      if (!empty($users)) {
        foreach ($users as $u) {
          $meta = get_user_meta($u->ID, '_bexio_data', true);
          $items[] = array(
            'ID' => $u->ID,
            'display_name' => $u->display_name,
            'user_email' => $u->user_email,
            'pv_bexio_sync' => json_decode($meta, true),
          );
        }
      }

      return new \WP_REST_Response(array(
        'offset' => $offset,
        'page_size' => $page_size,
        'total' => $total,
        'items' => $items,
      ), 200);
    }

    return new \WP_REST_Server(['message' => 'Method not allowed'], 405);
  }


  function products($request) {
    if ($request->get_method() === "GET") {
      $offset = intval($request->get_param('offset'));
      if ($offset < 0) $offset = 0;

      $page_size = intval($request->get_param('page_size'));
      if ($page_size <= 0) $page_size = 50; 
      $max_page_size = 200;
      if ($page_size > $max_page_size) $page_size = $max_page_size;

      $args = array(
        'limit' => $page_size,
        'offset' => $offset,
      );

      $products = wc_get_products($args);
      $all_products = wc_get_products(array('limit' => -1));
      $total = count($all_products);

      // build items array and include the `pv_bexio_sync` user meta
      $items = array();
      if (!empty($products)) {
        foreach ($products as $p) {
          $meta = $p->get_meta('_bexio_data', true);
          $items[] = array(
            'ID' => $p->get_id(),
            'name' => $p->get_name(),
            'sku' => $p->get_sku(),
            'pv_bexio_sync' => json_decode($meta, true),
          );
        }
      }

      return new \WP_REST_Response(array(
        'offset' => $offset,
        'page_size' => $page_size,
        'total' => $total,
        'items' => $items,
      ), 200);
    }

    return new \WP_REST_Server(['message' => 'Method not allowed'], 405);
  }


  function orders($request) {
    if ($request->get_method() === "GET") {
      $offset = intval($request->get_param('offset'));
      if ($offset < 0) $offset = 0;

      $page_size = intval($request->get_param('page_size'));
      if ($page_size <= 0) $page_size = 50; 
      $max_page_size = 200;
      if ($page_size > $max_page_size) $page_size = $max_page_size;

      $args = array(
        'limit' => $page_size,
        'offset' => $offset,
      );

      $orders = wc_get_orders($args);
      $all_orders = wc_get_orders(array('limit' => -1));
      $total = count($all_orders);

      // build items array and include the `pv_bexio_sync` user meta
      $items = array();
      if (!empty($orders)) {
        foreach ($orders as $o) {
          $meta = $o->get_meta('pv_bexio_sync');
          $items[] = array(
            'ID' => $o->get_id(),
            'date' => $o->get_date_created()->date('Y-m-d H:i:s'),
            'customer' => $o->get_billing_first_name() . ' ' . $o->get_billing_last_name(),
            'price' => $o->get_total(),
            'status' => $o->get_status(),
            'pv_bexio_sync' => $meta,
          );
        }
      }

      return new \WP_REST_Response(array(
        'offset' => $offset,
        'page_size' => $page_size,
        'total' => $total,
        'items' => $items,
      ), 200);
    }

    return new \WP_REST_Server(['message' => 'Method not allowed'], 405);

  }

  function logs($request) {
    $upload_dir = wp_upload_dir();
    $log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'pv-bexio';
    $log_url    = trailingslashit( $upload_dir['baseurl']  ) . 'pv-bexio';

    $file_param = $request->get_param('file');

    if ( $file_param ) {
      // Security: only allow simple log filenames, no path traversal.
      $filename = basename( $file_param );
      if ( ! preg_match( '/^pv-bexio[\w\-]*\.log$/', $filename ) ) {
        return new \WP_REST_Response( ['message' => 'Invalid file name'], 400 );
      }

      $filepath = $log_dir . '/' . $filename;
      if ( ! file_exists( $filepath ) ) {
        return new \WP_REST_Response( ['message' => 'File not found'], 404 );
      }

      $raw_lines = $this->read_last_lines( $filepath, 50 );

      $parsed = [];
      foreach ( $raw_lines as $line ) {
        $line = trim( $line );
        if ( empty( $line ) ) continue;
        $decoded = json_decode( $line, true );
        $parsed[] = $decoded !== null ? $decoded : [ 'message' => $line, 'level_name' => 'INFO' ];
      }

      return new \WP_REST_Response( [
        'file'         => $filename,
        'lines'        => $parsed,
        'download_url' => $log_url . '/' . $filename,
      ], 200 );
    }

    // No file param → return list of available log files, newest first.
    if ( ! is_dir( $log_dir ) ) {
      return new \WP_REST_Response( ['files' => []], 200 );
    }

    $files = glob( $log_dir . '/pv-bexio*.log' ) ?: [];
    rsort( $files );

    return new \WP_REST_Response( [
      'files' => array_map( 'basename', $files ),
    ], 200 );
  }

  private function read_last_lines( string $filepath, int $n ): array {
    $all   = file( $filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( $all === false ) {
      return [];
    }
    return array_slice( $all, -$n );
  }

}

?>
