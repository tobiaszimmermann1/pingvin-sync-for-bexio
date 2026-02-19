<?php
/**
 * Plugin Name:     Pingvin Bexio Sync
 * Plugin URI:      https://pingvin.digital
 * Description:     Connects WooCommerce to Bexio and syncs products
 * Author:          Tobias Zimmermann
 * Author URI:      https://pingvin.digital
 * Text Domain:     pingvin-bexio-sync
 * Domain Path:     /languages
 * Requires Plugins: woocommerce
 * Version:         0.5.0
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Pingvin Bexio Sync
 */

namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Pingvin_Bexio_ProductSync {

  /**
   * Singleton instance.
   *
   * @var Pingvin_Bexio_ProductSync
   */
  private static $instance = null;

  /**
   * Get the singleton instance.
   *
   * @return Pingvin_Bexio_ProductSync
   */
  public static function get_instance() {
    if ( null === self::$instance ) {
        self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor is private to prevent direct instantiation.
   */
  private function __construct() {
    $this->define_constants();
    $this->includes();
    $this->init_hooks();
  }

  /**
   * Define plugin constants.
   */
  private function define_constants() {
    define( 'PV_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
    define( 'PV_PLUGIN_PATH',     plugin_dir_path( __FILE__ ) );
    define( 'PV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
    define( 'PV_PLUGIN_VERSION',  '0.5.0' );
  }

  /**
   * Include required files.
   */
  private function includes() {
    $required_files = [
      'vendor/autoload.php',
      'inc/pingvin-call.php',
      'inc/pingvin-logger-class.php',
      'inc/pingvin-connector-settings-class.php',
      'inc/pingvin-sync-engine-class.php',
      'inc/products/product-engine.php',
      'inc/products/product-worker.php',
      'inc/contacts/contact-engine.php',
      'inc/contacts/contact-worker.php',
      'inc/bexio-reference-data.php',
      'inc/orders/contact-push-helper.php',
      'inc/orders/order-push-worker.php',
      'inc/pingvin-rest-routes.php',
      'inc/pingvin-auth-class.php',
    ];

    foreach ( $required_files as $file ) {
      $path = PV_PLUGIN_PATH . $file;
      if ( file_exists( $path ) ) {
        require_once $path;
      }
    }
  }

  /**
   * Initialize hooks and actions.
   */
  private function init_hooks() {
    add_action( 'before_woocommerce_init', [ $this, 'declare_woocommerce_compatibility' ] );
    register_deactivation_hook( __FILE__, [ $this, 'on_deactivation' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'admin_load_scripts' ] );
    add_action( 'woocommerce_checkout_order_created', function( $order ) {
      PvOrderPushWorker::enqueue( $order->get_id() );
    } );

    // Initialize other classes.
    $this->initialize_classes();
  }

  /**
   * Declare WooCommerce HPOS compatibility.
   */
  public function declare_woocommerce_compatibility() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
  }

  /**
   * Plugin deactivation callback.
   */
  public function on_deactivation() {
      flush_rewrite_rules();
      delete_transient( 'pv_bexio_connector_status' );
      delete_transient( 'pv_bexio_connector_next' );
  }

  /**
   * Load admin styles.
   */
  public function admin_load_scripts($screen) {
    if("toplevel_page_pingvin-bexio-sync" !== $screen) return;
    wp_register_style( 'pv_dashboard_style', PV_PLUGIN_URL . 'styles/styles.css', false, PV_PLUGIN_VERSION );
    wp_enqueue_style( 'pv_dashboard_style' );
  }

  /**
   * Initialize other plugin classes.
   */
  private function initialize_classes() {
      new Pingvin_Bexio_ProductSync_Settings();
      //new PvBexioProductsSync();
      new PvProductSyncEngine();
      new PvProductSyncWorker();
      new PvContactSyncEngine();
      new PvContactSyncWorker();
      new PvBexioReferenceData();
      new PvOrderPushWorker();
      new PvBexioRestRoutes();
  }
}

// Initialize the plugin.
Pingvin_Bexio_ProductSync::get_instance();
