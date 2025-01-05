<?php
/**
 * Plugin Name:     Pingvin Bexio Sync - Produkte
 * Plugin URI:      https://pingvin.digital/pingvin-bexio-sync
 * Description:     Connects WooCommerce to Bexio and syncs products
 * Author:          Tobias Zimmermann
 * Author URI:      https://pingvin.digital/pingvin-bexio-sync
 * Text Domain:     pv_bexio_connector
 * Domain Path:     /languages
 * Requires Plugins: woocommerce
 * Version:         0.1.1
 *
 * @package         Pingvin Bexio Sync - Produkte
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
    define( 'PV_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); 
    define( 'PV_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
    define( 'PV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

  }

  /**
   * Include required files.
   */
  private function includes() {
    $required_files = [
      'libraries/action-scheduler/action-scheduler.php',
      'vendor/autoload.php',
      'inc/pingvin-call.php',
      'inc/pingvin-logger-class.php',
      'inc/pingvin-connector-settings-class.php',
      'inc/pingvin-scheduled-product-sync-class.php',
      'inc/pingvin-rest-routes.php',
      'inc/pingvin-auth-class.php',
    ];

    foreach ( $required_files as $file ) {
      $path = PV_PLUGIN_PATH . $file;
      if ( file_exists( $path ) ) {
        require_once $path;
      } else {
        error_log( "Missing file: $path" );
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
    add_action( 'init', [ $this, 'plugin_init' ] );

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
    if('pingvin_page_pingvin-bexio-sync' !== $screen) return;
    wp_register_style( 'pv_dashboard_style', PV_PLUGIN_URL . 'styles/styles.css?version=0.1.0', false, '0.1.0' );
    wp_enqueue_style( 'pv_dashboard_style' );

    // Google Font
    wp_enqueue_style('pv_google_font', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap', false);
    wp_enqueue_style( 'pv_google_font' );
  }

  /**
   * Plugin initialization.
   */
  public function plugin_init() {
      load_plugin_textdomain( 'pv_bexio_connector', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
  }

  /**
   * Initialize other plugin classes.
   */
  private function initialize_classes() {
      new Pingvin_Bexio_ProductSync_Settings();
      new PvBexioProductsSync();
      new PvBexioRestRoutes();

      // Initialize the plugin tracker
      $client = new \Appsero\Client( '1172fbed-e7cc-4a88-a91a-07151798c66f', 'Pingvin Bexio Sync - Produkte', __FILE__ );
      $client->insights()->init();
      \Appsero\Updater::init($client);
  }
}

// Initialize the plugin.
Pingvin_Bexio_ProductSync::get_instance();
