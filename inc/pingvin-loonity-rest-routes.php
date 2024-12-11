<?php 

// import API calling helper function
require_once 'pingvin-loonity-call.php';

class PvLoonityRestRoutes {

  function __construct() {
    add_action( 'rest_api_init', array($this, 'rest_routes'));
  }

  function rest_routes() {
    /*************************************
     * Loonity specific routes
     *////////////////////////////////////

    // get markets 
    register_rest_route( 'loonity/v1', '/market', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_market'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));

    // get a specific market
    register_rest_route( 'loonity/v1', '/market/(?P<market_id>\d+)', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_market'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      },
      'args' => array(
        'market_id' => array(
          'required' => false,
        ),
      ),
    ));

    // get products of a specific market
    register_rest_route( 'loonity/v1', '/market/(?P<market_id>\d+)/products', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_products'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      },
      'args' => array(
        'market_id' => array(
          'required' => false,
        ),
      ),
    ));

    /*************************************
     * Plugin specific routes
     *////////////////////////////////////
    
    // get sync settings
    register_rest_route( 'loonity/v1', '/syncSettings', array(
      'methods' => 'GET',
      'callback' => array($this, 'sync_settings'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // get transient
    register_rest_route( 'loonity/v1', '/transient', array(
      'methods' => 'GET',
      'callback' => array($this, 'transient'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));
    
    // save sync settings
    register_rest_route( 'loonity/v1', '/syncSettings', array(
      'methods' => 'POST',
      'callback' => array($this, 'sync_settings'), 
      'permission_callback' => function() {
        return current_user_can( 'edit_others_posts' );
      }
    ));

  }

  /*************************************
   * Callback functions
   *////////////////////////////////////

  function get_market($request) {
    if (empty($request['market_id'])) {
      $result = pv_api_call('GET', 'market');
      return $result;
    } else {
      $result = pv_api_call('GET', 'market/'.$request['market_id']);
      return $result;
    }
  }

  function sync_settings($request) {
    if ($request->get_method() === "GET") {
      $sync_options = get_option('pv_loonity_sync_options');
      if ($sync_options == false) return null;
      return $sync_options;
    }

    if ($request->get_method() === "POST") {
      $params = $request->get_json_params();
      $update = update_option( 'pv_loonity_sync_options', json_encode($params));

      if (!is_wp_error($update)) {
        return new WP_REST_Response(['message' => 'Sync Settings successfully saved', 'data' => $params], 200);
      } else {
        return new WP_REST_Response(['message' => $update->get_error_message()], 500);
      }
    }

    return new WP_REST_Response(['message' => 'Method not allowed'], 405);
  }

  function transient() {
    $transient_status = get_transient('pv_loonity_connector_status');
    $transient_next = get_transient('pv_loonity_connector_next');

    return array(
      'status' => $transient_status,
      'next' => $transient_next
    );
  }

  function get_products($request) {
    $products = pv_api_call('GET', 'market/'.$request['market_id'].'/prouddcts');
    return $products;
  }

}

?>