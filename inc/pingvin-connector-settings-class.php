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
    foreach ( $tax_classes as $tax_class ) {
      $taxes = WC_Tax::get_rates_for_tax_class( $tax_class );
      $all_tax_rates = array_merge( $all_tax_rates, $taxes );
    }

    return $all_tax_rates;
  }

  public function pv_productsync_add_plugin_page() {
    $menu = add_menu_page( 'Bexio Sync', 'Bexio Sync', 'manage_options', 'pingvin-bexio-sync', array($this, 'pv_productsync_create_connector_sub_page'), 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iMjc5LjAwMDAwMHB0IiBoZWlnaHQ9IjI1MC4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDI3OS4wMDAwMDAgMjUwLjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsMjUwLjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTEzMjQgMjEyOSBjLTY1IC0xMSAtMTc5IC01MiAtMjY5IC05NiAtMjUwIC0xMjUgLTQ1MSAtMzgyIC01NjUKLTcyMiAtNzEgLTIxMyAtOTAgLTM4NSAtOTAgLTgzMyBsMCAtMzQ4IDgyNiAwIDgyNiAwIC02NCA3MiBjLTIwMSAyMjcgLTQxOQo1MTAgLTUwNCA2NTQgLTczIDEyNCAtMTU1IDMxOCAtMTg4IDQ0OCAtODQgMzIzIDUzIDYxMSAyNzggNTgxIDg4IC0xMSAxNTkKLTYyIDE5MCAtMTM3IDkgLTIwIDIwIC03NyAyNiAtMTI2IDExIC0xMDAgMjggLTE1NyA2MCAtMjAxIGwyMiAtMjkgNDcgNDggYzY1CjY4IDk0IDc0IDMyNiA2NiBsMTkwIC02IC01NCAzMyBjLTI5IDE3IC0xMjAgNzYgLTIwMiAxMzAgLTEzMiA4NiAtMTU1IDEwNgotMjAzIDE3MCAtNjUgODggLTE2OSAxODcgLTIzOSAyMjcgLTg2IDUwIC0xOTYgODAgLTI4NSA3OSAtNDIgLTEgLTEwMCAtNQotMTI4IC0xMHoiLz4KPHBhdGggZD0iTTE1MjMgMTc4MCBjLTQ2IC0xOCAtMzcgLTkzIDExIC0xMDYgNjIgLTE1IDk0IDU2IDQ0IDk5IC0yMyAxOSAtMjQKMTkgLTU1IDd6Ii8+CjwvZz4KPC9zdmc+Cg==', 110 );
    add_action( "load-{$menu}", array($this, 'load_admin_js') );
  }

  function load_admin_js(){
    $tab = null;
    if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
      $tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    if ($tab !== 'settings') {
      add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_js') );
    }
  }

  function enqueue_admin_js(){
    wp_enqueue_script( 'pv_productsync_connector_script', PV_PLUGIN_URL . 'build/index.js?version=0.2.0', array('lodash', 'react', 'react-dom', 'react-jsx-runtime', 'wp-i18n'), '1.0.0', true );
    wp_localize_script( 'pv_productsync_connector_script', 'pvProductSyncAppLocalizer', array(
      'bexioApiUrl' => home_url('/wp-json/pingvin/v1'),
      'homeUrl' => home_url(),
      'adminUrl' => wp_parse_url( admin_url() )['path'] . 'admin.php?page=pingvin-bexio-sync&tab=sync',
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

    if ( isset( $_GET['change_token'] ) && $_GET['change_token'] === 'true' ) {
      check_admin_referer( 'pv_bexio_change_token' );
      delete_option( 'pv_bexio_api_key' );
      delete_option( 'pv_bexio_general_options' );
      wp_safe_redirect( admin_url( 'admin.php?page=pingvin-bexio-sync' ) );
      exit;
    }
    
    if (!$auth->has_auth_token()) {
      if ( isset( $_GET['auth'] ) && $_GET['auth'] === 'true' ) {
        check_admin_referer( 'pv_bexio_auth' );
        $token = $auth->pv_authenticate();
        if ($token) {
          wp_safe_redirect( admin_url( 'admin.php?page=pingvin-bexio-sync' ) );
          exit;
        }
      }

      $this->pv_bexio_general_options = get_option('pv_bexio_general_options');
      ?>
      <div class="wrap">
        <div class="pv_bexio_connector_title">
          <img src="<?php echo esc_url( PV_PLUGIN_URL . 'assets/pingvin_logo.png.webp' ); ?>" alt="Pingvin Logo" />
          <h1>Pingvin Bexio Sync</h1>
        </div>
        <div class="pv_bexio_connector_main bg-white">
          <h2><?php esc_html_e( 'Verbinde WooCommerce mit deinem Bexio Konto', 'pingvin-bexio-sync' ); ?></h2>
      <?php 

      if (!$auth->has_bexio_app()) {
        ?>
        <p><?php esc_html_e( 'Pingvin Bexio Sync verwendet verwendet die Bexio API, um WooCommerce direkt mit deinem Bexio Konto zu verbinden.', 'pingvin-bexio-sync' ); ?> <strong><?php esc_html_e( 'Es wird kein Server dazwischen geschalten und du brauchst keine weiteren Accounts oder Abos.', 'pingvin-bexio-sync' ); ?></strong> <?php esc_html_e( 'Dies benötigt einmalig etwas Aufwand und einige Einstellungen in deinem Bexio Konto.', 'pingvin-bexio-sync' ); ?></p>

        <div class="pv_manual_box">
          <h3>Anleitung:</h3>
          <ol>
            <li><?php esc_html_e( 'Logge dich mit deinem Bexio Konto beim Bexio Developer Portal ein:', 'pingvin-bexio-sync' ); ?> <a href="https://developer.bexio.com/" target="_blank">Bexio Developer Portal</a></li>
            <li><?php esc_html_e( 'Erstelle eine neue Bexio App und konfiguriere sie mit den folgenden Einstellungen.', 'pingvin-bexio-sync' ); ?></li>
            <p class="indent-left">
              <ol>
                <li><strong>Name of the app:</strong> <span class="mono"><?php echo esc_url( home_url() ); ?></span></li>
                <li><strong>Website of the app/company:</strong> <span class="mono"><?php echo esc_url( home_url() ); ?></span></li>
                <li><strong>Description:</strong> Leer lassen</li>
                <li><strong>Upload logo:</strong> Leer lassen</li>
                <li><strong>Allowed redirect URL:</strong> <span class="mono"><?php echo esc_url( admin_url( 'admin.php?page=pingvin-bexio-sync&auth=true' ) ); ?></span></li>
              </ol>
            </p>
            <li><?php esc_html_e( 'Kopiere die', 'pingvin-bexio-sync' ); ?> <strong>Client ID</strong> <?php esc_html_e( 'und das', 'pingvin-bexio-sync' ); ?> <strong>>Client Secret</strong> <?php esc_html_e( '(zu finden unter "App Details") in die vorgesehenen Felder unten', 'pingvin-bexio-sync' ); ?></li>
            <li><?php esc_html_e( 'Klicke auf "Einstellungen speichern"', 'pingvin-bexio-sync' ); ?></li>
          </ol>
        </div>

        <form method="post" action="options.php" class="pv_manual_box no-border">
          <h3><?php esc_html_e( 'Bexio App Einstellungen:', 'pingvin-bexio-sync' ); ?></h3>
          <?php
            settings_fields( 'pv_bexio_option_group' );
            do_settings_sections( 'pv_bexio_general_admin' );
            submit_button("Einstellungen speichern");
          ?>
        </form>
        <h2><?php esc_html_e( 'Funktioniert es nicht?', 'pingvin-bexio-sync' ); ?></h2>
        <p><?php esc_html_e( 'Hier findest du Hilfe:', 'pingvin-bexio-sync' ); ?> <a href="https://pingvin.digital/pingvin-bexio-sync" target="_blank"><?php esc_html_e( 'Dokumentation & Support', 'pingvin-bexio-sync' ); ?></a></p>

        <?php
      } else { 
        ?>
        <p><?php esc_html_e( 'Alles ist bereit, um WooCommerce mit Bexio zu verbinden.', 'pingvin-bexio-sync' ); ?></p>
        <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=pingvin-bexio-sync&auth=true' ), 'pv_bexio_auth' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Jetzt mit Bexio verbinden', 'pingvin-bexio-sync' ); ?></a></p>
        <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=pingvin-bexio-sync&change_token=true' ), 'pv_bexio_change_token' ) ); ?>"><?php esc_html_e( 'Bexio Authentifizierungseinstellungen zurücksetzen', 'pingvin-bexio-sync' ); ?></a></p>
        <?php
      } ?>
      </div>
      <?php

    } else {
      $this->pv_bexio_productsync_options = get_option( 'pv_bexio_productsync_options' );

      // Handle immediate reference data refresh triggered by the warning notice button.
      if ( isset( $_GET['pv_refresh_ref'] ) && check_admin_referer( 'pv_refresh_ref' ) ) {
        ( new PvBexioReferenceData() )->refresh();
        wp_safe_redirect( admin_url( 'admin.php?page=pingvin-bexio-sync&tab=settings&pv_refreshed=1' ) );
        exit;
      }
      ?>
  
      <div class="wrap">
        <div class="pv_bexio_connector_title title-setup">
          <img src="<?php echo esc_url( PV_PLUGIN_URL . 'assets/pingvin_logo.png.webp' ); ?>" alt="Pingvin Logo" />
          <h1>Pingvin Bexio Sync</h1>
        </div>
  
        <?php
        $active_tab = null;
          if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
              $active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
          } 
        ?>
  
        <h2 class="nav-tab-wrapper">
          <a href="?page=pingvin-bexio-sync&tab=sync" class="nav-tab <?php echo esc_attr( $active_tab == 'sync' || $active_tab == null ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Synchronisierung', 'pingvin-bexio-sync' ); ?></a>
          <a href="?page=pingvin-bexio-sync&tab=products" class="nav-tab <?php echo esc_attr( $active_tab == 'products' ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Produkte', 'pingvin-bexio-sync' ); ?></a>
          <a href="?page=pingvin-bexio-sync&tab=contacts" class="nav-tab <?php echo esc_attr( $active_tab == 'contacts' ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Kontakte', 'pingvin-bexio-sync' ); ?></a>
          <a href="?page=pingvin-bexio-sync&tab=orders" class="nav-tab <?php echo esc_attr( $active_tab == 'orders' ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Bestellungen', 'pingvin-bexio-sync' ); ?></a>
          <a href="?page=pingvin-bexio-sync&tab=settings" class="nav-tab <?php echo esc_attr( $active_tab == 'settings' ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Einstellungen', 'pingvin-bexio-sync' ); ?></a>
        </h2>
  
        <?php
          if ( $active_tab == 'sync' || $active_tab == null ) { ?>
            <div id="pv_sync"></div> <?php
          } elseif ( $active_tab == 'settings' ) { ?>
            <?php
              $ref_taxes_ok  = PvBexioReferenceData::get_taxes() !== null;
              $ref_users_ok  = PvBexioReferenceData::get_users() !== null;
              $taxes_stale   = (bool) get_option( 'pv_bexio_taxes_stale' );
              $users_stale   = (bool) get_option( 'pv_bexio_users_stale' );
              $show_warning  = ! $ref_taxes_ok || ! $ref_users_ok || $taxes_stale || $users_stale;
              $show_success  = isset( $_GET['pv_refreshed'] ) && $_GET['pv_refreshed'] === '1';

              if ( $show_success ) { ?>
                <div class="notice notice-success inline" style="margin-bottom:16px;">
                  <p><?php esc_html_e( 'Referenzdaten wurden erfolgreich aktualisiert.', 'pingvin-bexio-sync' ); ?></p>
                </div>
              <?php }

              if ( $show_warning ) {
                $parts = [];
                if ( ! $ref_taxes_ok || ! $ref_users_ok ) $parts[] = __( 'Referenzdaten fehlen', 'pingvin-bexio-sync' );
                if ( $taxes_stale ) $parts[] = __( 'Steuerliste hat sich geändert', 'pingvin-bexio-sync' );
                if ( $users_stale ) $parts[] = __( 'Benutzerliste hat sich geändert', 'pingvin-bexio-sync' );
                $refresh_url = wp_nonce_url(
                  admin_url( 'admin.php?page=pingvin-bexio-sync&tab=settings&pv_refresh_ref=1' ),
                  'pv_refresh_ref'
                );
                ?>
                <div class="notice notice-warning inline" style="display:flex;align-items:center;gap:16px;margin-bottom:16px;margin-top:16px;">
                  <p style="margin:0;"><strong><?php esc_html_e( 'Achtung:', 'pingvin-bexio-sync' ); ?></strong>
                    <?php echo esc_html( implode( ', ', $parts ) ); ?>.
                  </p>
                  <a href="<?php echo esc_url( $refresh_url ); ?>" class="margin-left:10px;" style="white-space:nowrap;flex-shrink:0;">
                    <?php esc_html_e( 'Jetzt aktualisieren', 'pingvin-bexio-sync' ); ?>
                  </a>
                </div>
            <?php } ?>
            <div class="pv_bexio_connector_main">
              <form method="post" action="options.php" class="">
                <?php
                  settings_fields( 'pv_productsync_option_group' );
                  do_settings_sections( 'pv_productsync_admin' );
                  submit_button();
                ?>
                <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=pingvin-bexio-sync&change_token=true' ), 'pv_bexio_change_token' ) ); ?>"><?php esc_html_e( 'Bexio Authentifizierungseinstellungen zurücksetzen', 'pingvin-bexio-sync' ); ?></a></p>
              </form>
            </div> <?php
          }  elseif ( $active_tab == 'products' ) { ?>
            <div id="pv_products"></div>
            </div> <?php
          } elseif ( $active_tab == 'contacts' ) { ?>
            <div id="pv_contacts"></div> <?php
          } elseif ( $active_tab == 'orders' ) { ?>
            <div id="pv_orders"></div> <?php
          }
    }
  }

  public function pv_productsync_page_init() {
    register_setting(
      'pv_productsync_option_group',
      'pv_bexio_productsync_options',
      array( $this, 'pv_productsync_sanitize' )
    );

    // Action settings (managed via REST, registered here for sanitization + autoload control).
    register_setting(
      'pv_action_settings_group',
      'pv_bexio_productsync_action_settings',
      [ 'sanitize_callback' => [ $this, 'sanitize_action_settings' ] ]
    );
    register_setting(
      'pv_action_settings_group',
      'pv_bexio_contactsync_action_settings',
      [ 'sanitize_callback' => [ $this, 'sanitize_action_settings' ] ]
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
      __('Client ID deiner Bexio App','pingvin-bexio-sync'),
      array( $this, 'pv_bexio_client_id_callback' ),
      'pv_bexio_general_admin',
      'pv_bexio_general_setting_section'
    );

    add_settings_field(
      'pv_bexio_client_secret',
      __('Client Secret deiner Bexio App','pingvin-bexio-sync'),
      array( $this, 'pv_bexio_client_secret_callback' ),
      'pv_bexio_general_admin',
      'pv_bexio_general_setting_section'
    );

    add_settings_field(
      'pv_productsync_sync_direction',
      __('Synchronisierungs-Richtung <p><small style="font-weight:400;">Wähle die Quelle und das Ziel der Synchronisierung.</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_sync_direction_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    add_settings_field(
      'pv_productsync_missing_products',
      __('Fehlende Produkte Workflow <p><small style="font-weight:400;">Was soll mit Produkten getan werden, die im Ziel vorhanden sind, aber in der Quelle fehlen?</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_missing_products_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    add_settings_field(
      'pv_productsync_tax_rate_standard_bexio',
      __('Normalsteuersatz in Bexio <p><small style="font-weight:400;">MWST Code</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_standard_bexio_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    /*
    add_settings_field(
      'pv_productsync_tax_rate_standard_bexio_expense',
      __('Normalsteuersatz Vorsteuer in Bexio <p><small style="font-weight:400;">MWST Code</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_standard_bexio_expense_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );
    */

    add_settings_field(
      'pv_productsync_tax_rate_standard_woo',
      __('Normalsteuersatz in WooCommerce <p><small style="font-weight:400;">Steuerklasse</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_standard_woo_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    add_settings_field(
      'pv_productsync_tax_rate_reduced_bexio',
      __('Reduzierter Steuersatz in Bexio <p><small style="font-weight:400;">MWST Code</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_reduced_bexio_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    /*
    add_settings_field(
      'pv_productsync_tax_rate_reduced_bexio_expense',
      __('Reduzierter Steuersatz Vorsteuer in Bexio <p><small style="font-weight:400;">MWST Code</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_reduced_bexio_expense_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );
    */

    add_settings_field(
      'pv_productsync_tax_rate_reduced_bexio_woo',
      __('Reduzierter Steuersatz in WooCommerce <p><small style="font-weight:400;">Steuerklasse</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_reduced_woo_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    add_settings_field(
      'pv_productsync_tax_rate_special_bexio',
      __('Sondersatz für Beherbergung in Bexio <p><small style="font-weight:400;">MWST Code</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_special_bexio_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    /*
    add_settings_field(
      'pv_productsync_tax_rate_special_bexio_expense',
      __('Sondersatz für Beherbergung Vorsteuer in Bexio <p><small style="font-weight:400;">MWST Code</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_special_bexio_expense_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );
    */

    add_settings_field(
      'pv_productsync_tax_rate_special_bexio_woo',
      __('Sondersatz für Beherbergung in WooCommerce <p><small style="font-weight:400;">Steuerklasse</small></p>','pingvin-bexio-sync'),
      array( $this, 'pv_productsync_tax_rate_special_woo_callback' ),
      'pv_productsync_admin',
      'pv_productsync_setting_section'
    );

    add_settings_field(
      'pv_bexio_default_user_id',
      __('Standard Bexio Benutzer <p><small style="font-weight:400;">Wird für neue Bestellungen in Bexio verwendet.</small></p>', 'pingvin-bexio-sync'),
      array( $this, 'pv_bexio_default_user_callback' ),
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

    if ( isset( $input['pv_productsync_tax_rate_standard_bexio'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_standard_bexio'] = sanitize_text_field( $input['pv_productsync_tax_rate_standard_bexio'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_standard_bexio_expense'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_standard_bexio_expense'] = sanitize_text_field( $input['pv_productsync_tax_rate_standard_bexio_expense'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_standard_woo'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_standard_woo'] = sanitize_text_field( $input['pv_productsync_tax_rate_standard_woo'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_reduced_bexio'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_reduced_bexio'] = sanitize_text_field( $input['pv_productsync_tax_rate_reduced_bexio'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_reduced_bexio_expense'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_reduced_bexio_expense'] = sanitize_text_field( $input['pv_productsync_tax_rate_reduced_bexio_expense'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_reduced_woo'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_reduced_woo'] = sanitize_text_field( $input['pv_productsync_tax_rate_reduced_woo'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_special_bexio'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_special_bexio'] = sanitize_text_field( $input['pv_productsync_tax_rate_special_bexio'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_special_bexio_expense'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_special_bexio_expense'] = sanitize_text_field( $input['pv_productsync_tax_rate_special_bexio_expense'] );
    }

    if ( isset( $input['pv_productsync_tax_rate_special_woo'] ) ) {
      $sanitary_values['pv_productsync_tax_rate_special_woo'] = sanitize_text_field( $input['pv_productsync_tax_rate_special_woo'] );
    }

    if ( isset( $input['pv_bexio_default_user_id'] ) ) {
      $sanitary_values['pv_bexio_default_user_id'] = absint( $input['pv_bexio_default_user_id'] );
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

  /**
   * Sanitizes the {enabled, interval} JSON blob saved for action settings.
   * Called automatically when the option is saved through the WP settings API,
   * and used as a reference for the REST route sanitization.
   *
   * @param string|array $input  Raw option value (JSON string or decoded array).
   * @return string  JSON-encoded sanitized value.
   */
  public function sanitize_action_settings( $input ): string {
    $data = is_string( $input ) ? json_decode( $input, true ) : (array) $input;
    if ( ! is_array( $data ) ) return '{}';

    $allowed_intervals = [ 60, 120, 300, 3600, 14400, 86400 ];
    $interval = (int) ( $data['interval'] ?? 0 );

    return json_encode( [
      'enabled'  => ! empty( $data['enabled'] ),
      'interval' => in_array( $interval, $allowed_intervals, true ) ? $interval : 900,
    ] );
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
    $select = '';

    if($value == "true") $keep = 'selected';
    if($value == "false") $delete = 'selected';
    if($value == null) $select = 'selected';
    

    echo '<select name="pv_bexio_productsync_options[pv_productsync_missing_products]" id="pv_bexio_productsync_options[pv_productsync_missing_products]">';
    echo '<option value="0" ' . esc_attr( $select ) . '>' . esc_html__( 'Wähle eine Option', 'pingvin-bexio-sync' ) . '</option>';
    echo '<option value="true" ' . esc_attr( $keep ) . '>' . esc_html__( 'Behalte die Produkte im Ziel', 'pingvin-bexio-sync' ) . '</option>';
    echo '<option value="false" ' . esc_attr( $delete ) . '>' . esc_html__( 'Lösche die Produkte im Ziel', 'pingvin-bexio-sync' ) . '</option>';
    echo '</select>';
  }

  public function pv_productsync_sync_direction_callback() {
    $value = null;
    if ($this->pv_bexio_productsync_options) $value = $this->pv_bexio_productsync_options['pv_productsync_sync_direction'];
    $to_bexio = '';
    $from_bexio = '';
    $select = '';

    if($value == "to_bexio") $to_bexio = 'selected';
    if($value == "from_bexio") $from_bexio = 'selected';
    if($value == null) $select = 'selected';
    

    echo '<select name="pv_bexio_productsync_options[pv_productsync_sync_direction]" id="pv_bexio_productsync_options[pv_productsync_sync_direction]">';
    echo '<option value="0" ' . esc_attr( $select ) . '>' . esc_html__( 'Wähle eine Option', 'pingvin-bexio-sync' ) . '</option>';
    echo '<option value="from_bexio" ' . esc_attr( $from_bexio ) . '>' . esc_html__( 'Von Bexio (Quelle) zu WooCommerce (Ziel)', 'pingvin-bexio-sync' ) . '</option>';
    //echo '<option value="to_bexio" '.$to_bexio.'>'.__("Von WooCommerce (Quelle) zu Bexio (Ziel)", "pingvin-bexio-sync").'</option>';
    echo '</select>';
  }

  public function pv_productsync_tax_rate_standard_bexio_callback() {
    $saved = isset( $this->pv_bexio_productsync_options['pv_productsync_tax_rate_standard_bexio'] ) ? (string) $this->pv_bexio_productsync_options['pv_productsync_tax_rate_standard_bexio'] : '';
    $this->render_bexio_tax_select( 'pv_productsync_tax_rate_standard_bexio', $saved );
  }

  public function pv_productsync_tax_rate_standard_bexio_expense_callback() {
    $saved = isset( $this->pv_bexio_productsync_options['pv_productsync_tax_rate_standard_bexio_expense'] ) ? (string) $this->pv_bexio_productsync_options['pv_productsync_tax_rate_standard_bexio_expense'] : '';
    $this->render_bexio_tax_select( 'pv_productsync_tax_rate_standard_bexio_expense', $saved );
  }

  public function pv_productsync_tax_rate_standard_woo_callback() {
    $tax_classes = wc_get_product_tax_class_options();

    $value = null;
    if (isset($this->pv_bexio_productsync_options['pv_productsync_tax_rate_standard_woo'])) $value = $this->pv_bexio_productsync_options['pv_productsync_tax_rate_standard_woo'];
    $select = '';
    if ($value == null) $select = 'selected';

    echo '<select name="pv_bexio_productsync_options[pv_productsync_tax_rate_standard_woo]" id="pv_bexio_productsync_options[pv_productsync_tax_rate_standard_woo]">';
    echo '<option value="0" ' . esc_attr( $select ) . '>' . esc_html__( 'Wähle eine Option', 'pingvin-bexio-sync' ) . '</option>';
    foreach ( $tax_classes as $slug => $name ) {
      echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $value, $slug, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select>';
  }

  public function pv_productsync_tax_rate_reduced_bexio_callback() {
    $saved = isset( $this->pv_bexio_productsync_options['pv_productsync_tax_rate_reduced_bexio'] ) ? (string) $this->pv_bexio_productsync_options['pv_productsync_tax_rate_reduced_bexio'] : '';
    $this->render_bexio_tax_select( 'pv_productsync_tax_rate_reduced_bexio', $saved );
  }

  public function pv_productsync_tax_rate_reduced_bexio_expense_callback() {
    $saved = isset( $this->pv_bexio_productsync_options['pv_productsync_tax_rate_reduced_bexio_expense'] ) ? (string) $this->pv_bexio_productsync_options['pv_productsync_tax_rate_reduced_bexio_expense'] : '';
    $this->render_bexio_tax_select( 'pv_productsync_tax_rate_reduced_bexio_expense', $saved );
  }

  public function pv_productsync_tax_rate_reduced_woo_callback() {
    $tax_classes = wc_get_product_tax_class_options();

    $value = null;
    if (isset($this->pv_bexio_productsync_options['pv_productsync_tax_rate_reduced_woo'])) $value = $this->pv_bexio_productsync_options['pv_productsync_tax_rate_reduced_woo'];
    $select = '';
    if ($value == null) $select = 'selected';

    echo '<select name="pv_bexio_productsync_options[pv_productsync_tax_rate_reduced_woo]" id="pv_bexio_productsync_options[pv_productsync_tax_rate_reduced_woo]">';
    echo '<option value="0" ' . esc_attr( $select ) . '>' . esc_html__( 'Wähle eine Option', 'pingvin-bexio-sync' ) . '</option>';
    foreach ( $tax_classes as $slug => $name ) {
      echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $value, $slug, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select>';
  }

  public function pv_productsync_tax_rate_special_bexio_callback() {
    $saved = isset( $this->pv_bexio_productsync_options['pv_productsync_tax_rate_special_bexio'] ) ? (string) $this->pv_bexio_productsync_options['pv_productsync_tax_rate_special_bexio'] : '';
    $this->render_bexio_tax_select( 'pv_productsync_tax_rate_special_bexio', $saved );
  }

  public function pv_productsync_tax_rate_special_bexio_expense_callback() {
    $saved = isset( $this->pv_bexio_productsync_options['pv_productsync_tax_rate_special_bexio_expense'] ) ? (string) $this->pv_bexio_productsync_options['pv_productsync_tax_rate_special_bexio_expense'] : '';
    $this->render_bexio_tax_select( 'pv_productsync_tax_rate_special_bexio_expense', $saved );
  }

  public function pv_productsync_tax_rate_special_woo_callback() {
    $tax_classes = wc_get_product_tax_class_options();

    $value = null;
    if (isset($this->pv_bexio_productsync_options['pv_productsync_tax_rate_special_woo'])) $value = $this->pv_bexio_productsync_options['pv_productsync_tax_rate_special_woo'];
    $select = '';
    if ($value == null) $select = 'selected';

    echo '<select name="pv_bexio_productsync_options[pv_productsync_tax_rate_special_woo]" id="pv_bexio_productsync_options[pv_productsync_tax_rate_special_woo]">';
    echo '<option value="0" ' . esc_attr( $select ) . '>' . esc_html__( 'Wähle eine Option', 'pingvin-bexio-sync' ) . '</option>';
    foreach ( $tax_classes as $slug => $name ) {
      echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $value, $slug, false ) . '>' . esc_html( $name ) . '</option>';
    }
    echo '</select>';
  }
  /**
   * Renders a <select> populated from the cached Bexio tax list.
   * Stores the Bexio tax ID as the option value.
   * Shows a disabled placeholder when the cache is empty.
   */
  private function render_bexio_tax_select( string $field_key, string $saved_value ): void {
    $taxes = PvBexioReferenceData::get_taxes();
    $name  = 'pv_bexio_productsync_options[' . $field_key . ']';
    echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $field_key ) . '">';
    echo '<option value="">' . esc_html__( 'Wähle eine Option', 'pingvin-bexio-sync' ) . '</option>';
    if ( is_array( $taxes ) ) {
      foreach ( $taxes as $tax ) {
        $id    = (string) ( $tax['id'] ?? '' );
        $label = ! empty( $tax['display_name'] )
          ? $tax['display_name']
          : ( ( $tax['code'] ?? '' ) . ' (' . ( $tax['value'] ?? '' ) . '%)' );
        echo '<option value="' . esc_attr( $id ) . '" ' . selected( $id, $saved_value, false ) . '>' . esc_html( $label ) . '</option>';
      }
    } else {
      echo '<option value="" disabled>' . esc_html__( 'Keine Daten – zuerst Referenzdaten laden', 'pingvin-bexio-sync' ) . '</option>';
    }
    echo '</select>';
  }

  /**
   * Renders a <select> populated from the cached Bexio users list.
   * Stores the Bexio user ID as the option value.
   */
  public function pv_bexio_default_user_callback(): void {
    $saved = isset( $this->pv_bexio_productsync_options['pv_bexio_default_user_id'] )
      ? (int) $this->pv_bexio_productsync_options['pv_bexio_default_user_id']
      : 0;
    $users = PvBexioReferenceData::get_users();
    echo '<select name="pv_bexio_productsync_options[pv_bexio_default_user_id]" id="pv_bexio_default_user_id">';
    echo '<option value="0">' . esc_html__( 'Wähle einen Benutzer', 'pingvin-bexio-sync' ) . '</option>';
    if ( is_array( $users ) ) {
      foreach ( $users as $user ) {
        $id   = (int) ( $user['id'] ?? 0 );
        $name = trim( ( $user['firstname'] ?? '' ) . ' ' . ( $user['lastname'] ?? '' ) );
        if ( ! empty( $user['email'] ) ) {
          $name .= ' (' . $user['email'] . ')';
        }
        echo '<option value="' . esc_attr( (string) $id ) . '" ' . selected( $id, $saved, false ) . '>' . esc_html( $name ) . '</option>';
      }
    } else {
      echo '<option value="" disabled>' . esc_html__( 'Keine Daten – zuerst Referenzdaten laden', 'pingvin-bexio-sync' ) . '</option>';
    }
    echo '</select>';
  }}
