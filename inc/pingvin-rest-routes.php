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
    
    // save sync settings
    register_rest_route( 'pingvin/v1', '/syncSettings', array(
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

  function get_articles($request) {
    $result = pv_api_call('GET', '2.0/article');
    return $result;
  }

  function sync_settings($request) {
    if ($request->get_method() === "GET") {
      $sync_options = get_option('pv_bexio_productsync_action_settings');
      if ($sync_options == false) return null;
      return $sync_options;
    }

    if ($request->get_method() === "POST") {
      $params = $request->get_json_params();
      $update = update_option( 'pv_bexio_productsync_action_settings', json_encode($params));

      if (!is_wp_error($update)) {
        return new \WP_REST_Server(['message' => 'Sync Settings successfully saved', 'data' => $params], 200);
      } else {
        return new \WP_REST_Server(['message' => $update->get_error_message()], 500);
      }
    }

    return new \WP_REST_Server(['message' => 'Method not allowed'], 405);
  }

  function transient() {
    $transient_status = get_transient('pv_bexio_connector_status');
    $transient_next = get_transient('pv_bexio_connector_next');

    return array(
      'status' => $transient_status,
      'next' => $transient_next
    );
  }

}

?>