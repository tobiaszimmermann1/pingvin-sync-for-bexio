<?php

class Pingvin_Loonity_Connector_Settings {
  private $loonity_connector_options;

  public function __construct() {
    add_action( 'admin_menu', array( $this, 'loonity_connector_add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'loonity_connector_page_init' ) );
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

  public function loonity_connector_add_plugin_page() {
    $menu = add_submenu_page( 'edit.php?post_type=product', 'Loonity Connector', 'Loonity Connector', 'manage_options', 'loonity-connector', array($this, 'loonity_connector_create_connector_sub_page'), null );
    add_action( "load-{$menu}", array($this, 'load_admin_js') );
  }

  function load_admin_js(){
    add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_js') );
  }

  function enqueue_admin_js(){
    // javascript/react 
    wp_enqueue_script( 'pingvin_loonity_connector_script', PV_PLUGIN_URL . 'build/index.js?version=0.2.0', array('lodash', 'react', 'react-dom', 'react-jsx-runtime', 'wp-i18n'), '1.0.0' );
    wp_localize_script( 'pingvin_loonity_connector_script', 'pvLoonityAppLocalizer', array(
      'loonityApiUrl' => home_url('/wp-json/loonity/v1'),
      'homeUrl' => home_url(),
      'adminUrl' => parse_url(admin_url())['path'].'edit.php?post_type=product&page=loonity-connector',
      'pluginUrl' => plugin_dir_url(__FILE__),
      'nonce' => wp_create_nonce('wp_rest'),
      'currentUser' => (wp_get_current_user()),
      'settings' => get_option('loonity_connector_options'),
      "ajaxUrl" => admin_url('admin-ajax.php')
    ));
    wp_set_script_translations( 'pingvin_loonity_connector_script','pingvin_loonity_connector', plugin_dir_path( __FILE__ ) . '/languages' );
  }

  public function loonity_connector_create_admin_page_auth() {
    require_once( plugin_dir_path( __FILE__ ) . '/pingvin-loonity-call.php' );
  }

  public function loonity_connector_create_connector_sub_page() {
    $this->loonity_connector_options = get_option( 'loonity_connector_options' ); ?>

    <div class="wrap">
      <div class="pv_loonity_connector_title">
        <h2>WP Loonity Connector</h2>
        <img src="<?php echo plugin_dir_url(__DIR__).'assets/loonity_logo_light.png' ?>" class="pv_loonity_connector_title_logo" />
      </div>

      <?php
      $active_tab = null;
        if ( isset( $_GET['tab'] ) ) {
            $active_tab = $_GET['tab'];
        } 
      ?>

      <h2 class="nav-tab-wrapper">
        <a href="?post_type=product&page=loonity-connector&tab=sync" class="nav-tab <?php echo $active_tab == 'sync' || $active_tab == null ? 'nav-tab-active' : ''; ?>">Synchronization</a>
        <a href="?post_type=product&page=loonity-connector&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
      </h2>

      <?php
        if ( $active_tab == 'sync' || $active_tab == null ) { ?>
          <div id="pv_sync"></div> <?php
        } elseif ( $active_tab == 'settings' ) { ?>
          <form method="post" action="options.php" class="">
            <?php
              settings_fields( 'loonity_connector_option_group' );
              do_settings_sections( 'loonity-connector-admin' );
              submit_button();
            ?>
          </form> <?php
        }
        
      ?>
    <?php 
  }

  public function loonity_connector_page_init() {
    register_setting(
      'loonity_connector_option_group',
      'loonity_connector_options',
      array( $this, 'loonity_connector_sanitize' )
    );

    add_settings_section(
      'loonity_connector_setting_section',
      null,
      array( $this, 'loonity_connector_section_info' ),
      'loonity-connector-admin'
    );

    add_settings_field(
      'loonity_api_url',
      'Loonity WP API URL',
      array( $this, 'loonity_api_url_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_api_key_0',
      'Loonity WP API Authorization Token',
      array( $this, 'loonity_api_key_0_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_market_id',
      'Loonity Market ID <p><small style="font-weight:400;">The ID of the market in Loonity that products will be synced from</small></p>',
      array( $this, 'loonity_market_id_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_locale',
      'Loonity Locale <p><small style="font-weight:400;">Select the language in Loonity that products will be synced from</small></p>',
      array( $this, 'loonity_locale_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_missing_products',
      'Missing products workflow <p><small style="font-weight:400;">How to handle products that exist in WooCommerce, but not in Loonity?</small></p>',
      array( $this, 'loonity_missing_products_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_missing_producers',
      'Missing producers workflow <p><small style="font-weight:400;">How to handle producers that exist in WooCommerce, but not in Loonity?</small></p>',
      array( $this, 'loonity_missing_producers_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_tax_standard',
      'Standard VAT Tax Rate <p><small style="font-weight:400;">Select the WooCommerce tax rate that will be applied to products that have <strong>standard</strong> tax rate in Loonity</small></p>',
      array( $this, 'loonity_tax_standard_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );

    add_settings_field(
      'loonity_tax_reduced',
      'Reduced VAT Tax Rate <p><small style="font-weight:400;">Select the WooCommerce tax rate that will be applied to products that have <strong>reduced</strong> tax rate in Loonity</small></p>',
      array( $this, 'loonity_tax_reduced_callback' ),
      'loonity-connector-admin',
      'loonity_connector_setting_section'
    );
  }

  public function loonity_connector_sanitize($input) {
    $sanitary_values = array();
    if ( isset( $input['loonity_api_url'] ) ) {
      $sanitary_values['loonity_api_url'] = sanitize_text_field( $input['loonity_api_url'] );
    }

    if ( isset( $input['loonity_api_key_0'] ) ) {
      $sanitary_values['loonity_api_key_0'] = sanitize_text_field( $input['loonity_api_key_0'] );
    }

    if ( isset( $input['loonity_market_id'] ) ) {
      $sanitary_values['loonity_market_id'] = sanitize_text_field( $input['loonity_market_id'] );
    }

    if ( isset( $input['loonity_locale'] ) ) {
      $sanitary_values['loonity_locale'] = sanitize_text_field( $input['loonity_locale'] );
    }

    if ( isset( $input['loonity_missing_products'] ) ) {
      $sanitary_values['loonity_missing_products'] = sanitize_text_field( $input['loonity_missing_products'] );
    }

    if ( isset( $input['loonity_missing_producers'] ) ) {
      $sanitary_values['loonity_missing_producers'] = sanitize_text_field( $input['loonity_missing_producers'] );
    }

    if ( isset( $input['loonity_tax_standard'] ) ) {
      $sanitary_values['loonity_tax_standard'] = sanitize_text_field( $input['loonity_tax_standard'] );
    }

    if ( isset( $input['loonity_tax_reduced'] ) ) {
      $sanitary_values['loonity_tax_reduced'] = sanitize_text_field( $input['loonity_tax_reduced'] );
    }

    return $sanitary_values;
  }

  public function loonity_connector_section_info() {
  }

  public function loonity_api_key_0_callback() {
    printf(
      '<input class="large-text" type="text" name="loonity_connector_options[loonity_api_key_0]" id="loonity_api_key_0" value="%s">',
      isset( $this->loonity_connector_options['loonity_api_key_0'] ) ? esc_attr( $this->loonity_connector_options['loonity_api_key_0']) : ''
    );
  }

  public function loonity_api_url_callback() {
    printf(
      '<input class="large-text" type="text" name="loonity_connector_options[loonity_api_url]" id="loonity_api_url" value="%s">',
      isset( $this->loonity_connector_options['loonity_api_url'] ) ? esc_attr( $this->loonity_connector_options['loonity_api_url']) : ''
    );
  }

  public function loonity_market_id_callback() {
    printf(
      '<input class="small-text" type="number" name="loonity_connector_options[loonity_market_id]" id="loonity_market_id" value="%s">',
      isset( $this->loonity_connector_options['loonity_market_id'] ) ? esc_attr( $this->loonity_connector_options['loonity_market_id']) : ''
    );
  }

  public function loonity_locale_callback() {
    $value = $this->loonity_connector_options['loonity_locale'];
    $de = '';
    $it = '';
    $en = '';
    //$fr = '';

    if($value == 'de') $de = 'selected';
    if($value == 'it') $it = 'selected';
    if($value == 'en') $en = 'selected';
    //if($value == 'fr') $fr = 'selected';
    

    echo '<select name="loonity_connector_options[loonity_locale]" id="loonity_connector_options[loonity_locale]">';
    echo '<option value="de" '.$de.'>de</option>';
    echo '<option value="it" '.$it.'>it</option>';
    echo '<option value="en" '.$en.'>en</option>';
    //echo '<option value="fr" '.$fr.'>fr</option>';
    echo '</select>';
  }

  public function loonity_missing_products_callback() {
    $value = $this->loonity_connector_options['loonity_missing_products'];
    $keep = '';
    $delete = '';

    if($value == "true") $keep = 'selected';
    if($value == "false") $delete = 'selected';
    

    echo '<select name="loonity_connector_options[loonity_missing_products]" id="loonity_connector_options[loonity_missing_products]">';
    echo '<option value="true" '.$keep.'>Keep products in WooCommerce</option>';
    echo '<option value="false" '.$delete.'>Delete products from WooCommerce</option>';
    echo '</select>';
  }

  public function loonity_missing_producers_callback() {
    $value = $this->loonity_connector_options['loonity_missing_producers'];
    $keep = '';
    $delete = '';

    if($value == "true") $keep = 'selected';
    if($value == "false") $delete = 'selected';
    

    echo '<select name="loonity_connector_options[loonity_missing_producers]" id="loonity_connector_options[loonity_missing_producers]">';
    echo '<option value="true" '.$keep.'>Keep producers in WooCommerce</option>';
    echo '<option value="false" '.$delete.'>Delete producers from WooCommerce</option>';
    echo '</select>';
  }

  public function loonity_tax_standard_callback() {
    $value = $this->loonity_connector_options['loonity_tax_standard'];
    $tax_rates = $this->get_woo_tax_rates();

    echo '<select name="loonity_connector_options[loonity_tax_standard]" id="loonity_connector_options[loonity_tax_standard]">';
    foreach ($tax_rates as $tax_rate) {
      $selected = '';
      if ($value === $tax_rate->tax_rate_class) {
        $selected = 'selected';
      }
      echo '<option value="'.$tax_rate->tax_rate_class.'" '.$selected.'>'.$tax_rate->tax_rate_name.'</option>';
    }
    echo '</select>';
  }

  public function loonity_tax_reduced_callback() {
    $value = $this->loonity_connector_options['loonity_tax_reduced'];
    $tax_rates = $this->get_woo_tax_rates();

    echo '<select name="loonity_connector_options[loonity_tax_reduced]" id="loonity_connector_options[loonity_tax_reduced]">';
    foreach ($tax_rates as $tax_rate) {
      $selected = '';
      if ($value === $tax_rate->tax_rate_class) {
        $selected = 'selected';
      }
      echo '<option value="'.$tax_rate->tax_rate_class.'" '.$selected.'>'.$tax_rate->tax_rate_name.'</option>';
    }
    echo '</select>';
  }

}