<?php
/**
 * Plugin Name:     WP Loonity Connector
 * Plugin URI:      https://loonity.com
 * Description:     Connects WooCommerce to Loonity
 * Author:          Tobias Zimmermann
 * Author URI:      https://pingvin.digital
 * Text Domain:     pv_loonity_connector
 * Domain Path:     /languages
 * Version:         0.2.0
 *
 * @package         WP Loonity Connector
 */

 /**
 * CONSTANTS
 */
define ('PV_PLUGIN_URL', plugin_dir_url(__FILE__));


/**
 * Includes
 */
require_once( plugin_dir_path( __FILE__ ) . '/libraries/action-scheduler/action-scheduler.php' );
require_once( plugin_dir_path( __FILE__ ) . '/vendor/autoload.php' );
require_once( plugin_dir_path( __FILE__ ) . '/inc/pingvin-loonity-call.php' );


/**
 * Declare WooCommerce HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

/**
 * Run on activation of plugin
 */
function register_producers_post_type() {
  // check if the 'producers' post type exists [compatible with POT Plugin]
  $producers_post_type = 'producers';
  if (!post_type_exists($producers_post_type)) {
    // if it cpt 'producers' doesnt exist, create it
    $labels_producers = array(
      'name'                  => __( 'Producers'),
      'singular_name'         => __( 'Producer'),
      'menu_name'             => __( 'Produzenten'),
      'name_admin_bar'        => __( 'Produzenten'),
      'archives'              => __( 'Produzentenarchiv'),
      'all_items'             => __( 'Alle Produzenten'),
      'add_new_item'          => __( 'Neuer Produzent hinzufügen'),
      'add_new'               => __( 'Hinzufügen'),
      'new_item'              => __( 'Produzent hinzufügen'),
      'edit_item'             => __( 'Produzent bearbeiten'),
      'update_item'           => __( 'Produzent speichern'),
      'view_item'             => __( 'Produzent ansehen'),
      'view_items'            => __( 'Produzenten ansehen'),
    );

    $args_producers = array(
      'label'                 => __( 'Produzenten'),
      'labels'                => $labels_producers,
      'supports'              => array('author', 'custom-fields', 'title', 'editor', 'excerpt', 'thumbnail'),
      'taxonomies'            => array(),
      'hierarchical'          => false,
      'public'                => true,
      'show_ui'               => true,
      'show_in_menu'          => true,
      'show_in_admin_bar'     => false,
      'show_in_nav_menus'     => false,
      'can_export'            => true,
      'has_archive'           => true,
      'exclude_from_search'   => true,
      'publicly_queryable'    => true,
      'capability_type'       => 'post',
      'rewrite' => array('slug' => 'producers'),
    );
    register_post_type( 'producers', $args_producers );
    flush_rewrite_rules();
  }
}
add_action('init', 'register_producers_post_type');

/**
 * Run on deactivation of plugin
 */
function pingvin_deactivate_loonity_connector() {
  flush_rewrite_rules();
  delete_transient('pv_loonity_connector_status');
  delete_transient('pv_loonity_connector_next');
}
register_deactivation_hook( __FILE__, 'pingvin_deactivate_loonity_connector' );

/**
 * Plugin initialization:
 * ** Loading scripts
 * ** Loading styles
 * ** Loading Text Domain
 */
add_action( 'admin_enqueue_scripts', 'pingvin_admin_load_scripts');
function pingvin_admin_load_scripts() {
  wp_register_style( 'pv_dashboard_style', plugin_dir_url( __FILE__ ).'styles/styles.css?version=0.2.0', false, '0.2.0' );
  wp_enqueue_style( 'pv_dashboard_style');
}


add_action( 'init', 'pingvin_loonity_connector_init');
function pingvin_loonity_connector_init() {
  // text domain
  load_plugin_textdomain( 'pv_loonity_connector', false, dirname(plugin_basename(__FILE__)) . '/languages' );
};

/**
 * Initialize pingvin logger
 */
require_once( plugin_dir_path( __FILE__ ) . 'inc/pingvin-logger-class.php');

/**
 * Plugin Admin Page
 */
require_once( plugin_dir_path( __FILE__ ) . 'inc/pingvin-loonity-connector-settings-class.php');
$pv_loonity_connector_plugin = new Pingvin_Loonity_Connector_Settings();

/**
 * Action Scheduler: Schedule sync operations
 */
require_once( plugin_dir_path( __FILE__ ) . 'inc/pingvin-scheduled-product-sync-class.php');
$pv_scheduled_product_sync = new PvLoonitySync();

/**
 * Initialize rest routes
 */
require_once( plugin_dir_path( __FILE__ ) . 'inc/pingvin-loonity-rest-routes.php');
$pv_rest_routes = new PvLoonityRestRoutes();


/**
 * Display Loonity info in product data tab
 */

 require_once( plugin_dir_path( __FILE__ ) . 'inc/pingvin-loonity-product-data-display.php');
 $pv_product_data_display = new PvLoonityProductData();