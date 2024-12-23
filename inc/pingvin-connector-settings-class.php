<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Pingvin_Bexio_ProductSync_Settings {
  private $pv_bexio_productsync_options;
  private $pv_bexio_general_options;

  public function __construct() {
    add_action( 'admin_menu', array( $this, 'pv_productsync_add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'pv_productsync_page_init' ) );
  }

  public function get_woo_tax_rates() {
    $all_tax_rates = [];
    $tax_classes = WC_Tax::get_tax_classes();
    if ( !in_array( '', $tax_classes ) ) {
      array_unshift( $tax_classes, '' );
    }
    foreach ( $tax_classes as $tax_class ) { // For each tax class, get all rates.
      $taxes = WC_Tax::get_rates_for_tax_class( $tax_class );
      $all_tax_rates = array_merge( $all_tax_rates, $taxes );
    }

    return $all_tax_rates;
  }

  public function pv_productsync_add_plugin_page() {
    $menu = add_menu_page( 'Pingvin', 'Pingvin', 'manage_options', 'pingvin', 'pingvin_menu_page', 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iMjc5LjAwMDAwMHB0IiBoZWlnaHQ9IjI1MC4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDI3OS4wMDAwMDAgMjUwLjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsMjUwLjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTEzMjQgMjEyOSBjLTY1IC0xMSAtMTc5IC01MiAtMjY5IC05NiAtMjUwIC0xMjUgLTQ1MSAtMzgyIC01NjUKLTcyMiAtNzEgLTIxMyAtOTAgLTM4NSAtOTAgLTgzMyBsMCAtMzQ4IDgyNiAwIDgyNiAwIC02NCA3MiBjLTIwMSAyMjcgLTQxOQo1MTAgLTUwNCA2NTQgLTczIDEyNCAtMTU1IDMxOCAtMTg4IDQ0OCAtODQgMzIzIDUzIDYxMSAyNzggNTgxIDg4IC0xMSAxNTkKLTYyIDE5MCAtMTM3IDkgLTIwIDIwIC03NyAyNiAtMTI2IDExIC0xMDAgMjggLTE1NyA2MCAtMjAxIGwyMiAtMjkgNDcgNDggYzY1CjY4IDk0IDc0IDMyNiA2NiBsMTkwIC02IC01NCAzMyBjLTI5IDE3IC0xMjAgNzYgLTIwMiAxMzAgLTEzMiA4NiAtMTU1IDEwNgotMjAzIDE3MCAtNjUgODggLTE2OSAxODcgLTIzOSAyMjcgLTg2IDUwIC0xOTYgODAgLTI4NSA3OSAtNDIgLTEgLTEwMCAtNQotMTI4IC0xMHoiLz4KPHBhdGggZD0iTTE1MjMgMTc4MCBjLTQ2IC0xOCAtMzcgLTkzIDExIC0xMDYgNjIgLTE1IDk0IDU2IDQ0IDk5IC0yMyAxOSAtMjQKMTkgLTU1IDd6Ii8+CjwvZz4KPC9zdmc+Cg==', 70 );
    $submenu = add_submenu_page( 'pingvin', 'Bexio Sync', 'Bexio Sync', 'manage_options', 'pingvin-bexio-product-sync', array($this, 'pv_productsync_create_connector_sub_page'), null );
    add_action( "load-{$submenu}", array($this, 'load_admin_js') );
  }

  function load_admin_js(){
    add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_js') );
  }

  function enqueue_admin_js(){
    // javascript/react 
    wp_enqueue_script( 'pv_productsync_connector_script', PV_PLUGIN_URL . 'build/index.js?version=0.2.0', array('lodash', 'react', 'react-dom', 'react-jsx-runtime', 'wp-i18n'), '1.0.0' );
    wp_localize_script( 'pv_productsync_connector_script', 'pvProductSyncAppLocalizer', array(
      'bexioApiUrl' => home_url('/wp-json/pingvin/v1'),
      'homeUrl' => home_url(),
      'adminUrl' => parse_url(admin_url())['path'].'admin.php?page=pingvin-bexio-product-sync&tab=sync',
      'pluginUrl' => plugin_dir_url(__FILE__),
      'nonce' => wp_create_nonce('wp_rest'),
      'currentUser' => (wp_get_current_user()),
      'settings' => get_option('pv_productsync_options'),
      "ajaxUrl" => admin_url('admin-ajax.php')
    ));
    wp_set_script_translations( 'pv_productsync_connector_script','pv_productsync_connector', plugin_dir_path( __FILE__ ) . '/languages' );
  }

  /*
  public function pv_productsync_create_admin_page_auth() {
    require_once( plugin_dir_path( __FILE__ ) . '/pingvin-call.php' );
  }
  */

  public function pv_productsync_create_connector_sub_page() { 
    $auth = Pingvin_Bexio_ProductSync_Auth::get_instance();
    $change_token = false;

    if (isset($_GET['change_token'])) {
      if ($_GET['change_token'] == 'true') {
        delete_option( 'pv_bexio_api_key' );
        delete_option( 'pv_bexio_general_options' );
        wp_redirect( admin_url('admin.php?page=pingvin-bexio-product-sync') );
      }
    }
    
    if (!$auth->has_auth_token()) {
      if (isset($_GET['auth'])) {
        if ($_GET['auth'] == 'true') {
          $token = $auth->pv_authenticate();
          if ($token) {
            wp_redirect( admin_url('admin.php?page=pingvin-bexio-product-sync') );
          }
        }
      }

      $this->pv_bexio_general_options = get_option('pv_bexio_general_options');
      ?>
      <div class="wrap">
        <div class="pv_pv_productsync_title">
          <h2>Verbinde WooCommerce mit deinem Bexio Konto</h2>
        </div>
      <?php 

      if (!$auth->has_bexio_app()) {
        ?>
        <p>Um die Verbindung zwischen WooCommerce und Bexio herzustellen, musst du eine Bexio App erstellen. <a href="https://developer.bexio.com/" target="_blank">Hier</a> kannst du eine App erstellen.</p>
        <p>Wenn du die App erstellt hast, kopiere die Client ID und den Client Secret in die vorgesehenen Felder hier.</p>
        <form method="post" action="options.php" class="">
          <?php
            settings_fields( 'pv_bexio_option_group' );
            do_settings_sections( 'pv_bexio_general_admin' );
            submit_button();
          ?>
        </form>
        <?php
      } else { 
        ?>
        <p>Alles ist bereit, um WooCommerce mit Bexio zu verbinden.</p>
        <p><a href="?page=pingvin-bexio-product-sync&auth=true" class="button button-primary">Jetzt mit Bexio verbinden</a></p>
        <?php
      }

    } else {
      $this->pv_bexio_productsync_options = get_option( 'pv_bexio_productsync_options' ); 
      ?>
  
      <div class="wrap">
        <div class="pv_pv_productsync_title">
          <h2>Pingvin Bexio Sync</h2>
        </div>
  
        <?php
        $active_tab = null;
          if ( isset( $_GET['tab'] ) ) {
              $active_tab = $_GET['tab'];
          } 
        ?>
  
        <h2 class="nav-tab-wrapper">
          <a href="?page=pingvin-bexio-product-sync&tab=sync" class="nav-tab <?php echo $active_tab == 'sync' || $active_tab == null ? 'nav-tab-active' : ''; ?>">Produkte</a>
          <a href="?page=pingvin-bexio-product-sync&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Einstellungen</a>
        </h2>
  
        <?php
          if ( $active_tab == 'sync' || $active_tab == null ) { ?>
            <div id="pv_sync"></div> <?php
          } elseif ( $active_tab == 'settings' ) { ?>
            <div class="pv_bexio_connector_main">
              <form method="post" action="options.php" class="">
                <?php
                  settings_fields( 'pv_productsync_option_group' );
                  do_settings_sections( 'pv_productsync_admin' );
                  submit_button();
                ?>
                <p><a href="?page=pingvin-bexio-product-sync&change_token=true">Authentifizierungseinstellungen zurücksetzen</a> (Du musst dich neu mit Bexio verbinden)</p>
              </form>
            </div> <?php
          }
          
        ?>
      <?php 
    }
  }

  public function pv_productsync_page_init() {
    register_setting(
      'pv_productsync_option_group',
      'pv_bexio_productsync_options',
      array( $this, 'pv_productsync_sanitize' )
    );

    add_settings_section(
      'pv_bexio_general_setting_section',
      null,
      array( $this, 'pv_bexio_general_section_info' ),
      'pv_bexio_general_admin'
    );

    register_setting(
      'pv_bexio_option_group',
      'pv_bexio_general_options',
      array( $this, 'pv_bexio_sanitize' )
    );

    add_settings_section(
      'pv_productsync_setting_section',
      null,
      array( $this, 'pv_productsync_section_info' ),
      'pv_productsync_admin'
    );

    add_settings_field(
      'pv_bexio_client_id',
      'Die "Client ID" deiner Bexio App',
      array( $this, 'pv_bexio_client_id_callback' ),
      'pv_bexio_general_admin',
      'pv_bexio_general_setting_section'
    );

    add_settings_field(
      'pv_bexio_client_secret',
      'Das "Client Secret" deiner Bexio App',
      array( $this, 'pv_bexio_client_secret_callback' ),
      'pv_bexio_general_admin',
      'pv_bexio_general_setting_section'
    );

    add_settings_field(
      'pv_productsync_sync_direction',
      'Synchronisierungs-Richtung <p><small style="font-weight:400;">Wähle die Quelle und das Ziel der Synchronisierung.</small></p>',
      array( $this, 'pv_productsync_sync_direction_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    add_settings_field(
      'pv_productsync_missing_products',
      'Fehlende Produkte Workflow <p><small style="font-weight:400;">Was soll mit Produkten getan werden, die im Ziel vorhanden sind, aber in der Quelle fehlen?</small></p>',
      array( $this, 'pv_productsync_missing_products_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );
  }

  public function pv_productsync_sanitize($input) {
    $sanitary_values = array();

    if ( isset( $input['pv_productsync_missing_products'] ) ) {
      $sanitary_values['pv_productsync_missing_products'] = sanitize_text_field( $input['pv_productsync_missing_products'] );
    }

    if ( isset( $input['pv_productsync_sync_direction'] ) ) {
      $sanitary_values['pv_productsync_sync_direction'] = sanitize_text_field( $input['pv_productsync_sync_direction'] );
    }

    return $sanitary_values;
  }

  public function pv_bexio_sanitize($input) {
    $sanitary_values = array();

    if ( isset( $input['pv_bexio_client_id'] ) ) {
      $sanitary_values['pv_bexio_client_id'] = sanitize_text_field( $input['pv_bexio_client_id'] );
    }

    if ( isset( $input['pv_bexio_client_secret'] ) ) {
      $sanitary_values['pv_bexio_client_secret'] = sanitize_text_field( $input['pv_bexio_client_secret'] );
    }

    return $sanitary_values;
  }

  public function pv_productsync_section_info() {
  }

  public function pv_bexio_general_section_info() {
  }

  public function pv_bexio_client_id_callback() {
    printf(
      '<input class="large-text" type="text" name="pv_bexio_general_options[pv_bexio_client_id]" id="pv_bexio_client_id" value="%s">',
      isset( $this->pv_bexio_general_options['pv_bexio_client_id'] ) ? esc_attr( $this->pv_bexio_general_options['pv_bexio_client_id']) : ''
    );
  }

  public function pv_bexio_client_secret_callback() {
    printf(
      '<input class="large-text" type="text" name="pv_bexio_general_options[pv_bexio_client_secret]" id="pv_bexio_client_secret" value="%s">',
      isset( $this->pv_bexio_general_options['pv_bexio_client_secret'] ) ? esc_attr( $this->pv_bexio_general_options['pv_bexio_client_secret']) : ''
    );
  }

  public function pv_productsync_missing_products_callback() {
    $value = null;
    if ($this->pv_bexio_productsync_options) $value = $this->pv_bexio_productsync_options['pv_productsync_missing_products'];
    $keep = '';
    $delete = '';

    if($value == "true") $keep = 'selected';
    if($value == "false") $delete = 'selected';
    if($value == null) $select = 'selected';
    

    echo '<select name="pv_bexio_productsync_options[pv_productsync_missing_products]" id="pv_bexio_productsync_options[pv_productsync_missing_products]">';
    echo '<option value="true" '.$select.'>Wähle eine Option</option>';
    echo '<option value="true" '.$keep.'>Behalte die Produkte im Ziel</option>';
    echo '<option value="false" '.$delete.'>Lösche die Produkte im Ziel</option>';
    echo '</select>';
  }

  public function pv_productsync_sync_direction_callback() {
    $value = null;
    if ($this->pv_bexio_productsync_options) $value = $this->pv_bexio_productsync_options['pv_productsync_sync_direction'];
    $to_bexio = '';
    $from_bexio = '';

    if($value == "true") $to_bexio = 'selected';
    if($value == "false") $from_bexio = 'selected';
    if($value == null) $select = 'selected';
    

    echo '<select name="pv_bexio_productsync_options[pv_productsync_sync_direction]" id="pv_bexio_productsync_options[pv_productsync_sync_direction]">';
    echo '<option value="true" '.$select.'>Wähle eine Option</option>';
    echo '<option value="false" '.$from_bexio.'>Von Bexio (Quelle) zu WooCommerce (Ziel)</option>';
    echo '<option value="true" '.$to_bexio.'>Von WooCommerce (Quelle) zu Bexio (Ziel)</option>';
    echo '</select>';
  }

}