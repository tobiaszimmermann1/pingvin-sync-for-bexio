<?php
namespace Pingvin;
use Jumbojett\OpenIDConnectClient;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Pingvin_Bexio_ProductSync_Auth {
  private static $instance = null;

  private function __construct() {}

  public static function get_instance() {
      if (null === self::$instance) {
          self::$instance = new self();
      }
      return self::$instance;
  }



  /**
   * Checks if an api token exists
   * 
   * @return bool
   */
  public function has_auth_token() {
    $option = get_option( 'pv_bexio_api_key' ); 
    if ($option) {
      return true;
    } else {
      return false;
    }
  }



  /**
   * Shows OpenID Authentication screen from Bexio
   * 
   * @return string|null
   */
  public function bexio_authenticate() {
    error_log('Starting Bexio authentication');
    $bexio_general_options = get_option( 'pv_bexio_general_options' );
    if (!$bexio_general_options) {
      PingvinLogger::log('error', 'Bexio general options not found');
      return null;
    }
    if (empty($bexio_general_options['pv_bexio_client_id']) || empty($bexio_general_options['pv_bexio_client_secret'])) {
      PingvinLogger::log('error', 'Client ID or Client Secret is missing');
      return null;
    }
    try {
      $oidc = new OpenIDConnectClient("https://auth.bexio.com/realms/bexio",  $bexio_general_options['pv_bexio_client_id'] , $bexio_general_options['pv_bexio_client_secret'] );
      $oidc->setRedirectURL(admin_url('admin.php?page=pingvin-bexio-product-sync&auth=true'));
      $oidc->addScope(array("openid", "company_profile", "email", "offline_access", "profile", "article_show", "article_edit", "stock_edit"));
      
      // Check if there is an authorization code in the URL
      if (isset($_GET['code'])) {
        PingvinLogger::log('info', 'Authorization code found in URL');
        $oidc->authenticate();
        $api_token = $oidc->getAccessToken();
        $refresh_token = $oidc->getRefreshToken();
        if (!$api_token) {
          PingvinLogger::log('error', 'Failed to retrieve API token');
          return null;
        }
        PingvinLogger::log('info', 'Token retrieved');
        PingvinLogger::log('info', 'Refresh Token retrieved');
        return array('api_token' => $api_token, 'refresh_token' => $refresh_token);
      } else {
        // Redirect to the OpenID Connect provider for authentication
        PingvinLogger::log('error', 'No authorization code found, redirecting to OIDC provider');
        $oidc->authenticate();
        exit;
      }
    } catch (Exception $e) {
      PingvinLogger::log('error', 'Error during authentication: ' . $e->getMessage());
      return null;
    }
  }



  /**
   * Run authentication with Bexio
   * 
   * @return bool
   */
  public function pv_authenticate() {
    error_log('Starting pv_authenticate');
    $tokens = $this->bexio_authenticate();
    
    PingvinLogger::log('info', 'Tokens: ' . print_r($tokens, true));
    if ($tokens && isset($tokens['api_token']) && isset($tokens['refresh_token'])) {
      $expiry_time = time() + 3600;
      $update_result_api = update_option( 'pv_bexio_api_key', $tokens['api_token'] );
      $update_result_expiry = update_option('pv_bexio_token_expiry', $expiry_time);
      PingvinLogger::log('info', 'Bexio API Token saved.');
      $update_result_refresh = update_option( 'pv_bexio_refresh_token', $tokens['refresh_token'] );
      PingvinLogger::log('info', 'Bexio Refresh Token saved.');
      return true;
    } else {
      PingvinLogger::log('error', 'Tokens are null, authentication failed');
      return false;
    }
  }



  /**
   * Checks if the user has created a bexio app on developer.bexio.com and saved credentials to wordpress
   * 
   * @return bool
   */
  public function has_bexio_app() {
    $client_secret = isset(get_option( "pv_bexio_general_options" )['pv_bexio_client_secret']);
    $client_id = isset(get_option( "pv_bexio_general_options" )['pv_bexio_client_id']);

    if ( $client_secret && $client_id ) {
      return true;
    } else {
      return false;
    }
  }



  /**
   * Checks if the API token is still valid
   */
  public function get_valid_api_token() {
    $api_token = get_option('pv_bexio_api_key');
    $refresh_token = get_option('pv_bexio_refresh_token');
    $token_expiry = get_option('pv_bexio_token_expiry');

    if (time() >= $token_expiry || !$api_token || !$refresh_token || !$token_expiry) {
      PingvinLogger::log('info', 'Invalid or expired tokens, attempting to use refresh token to get new tokens');
      $new_tokens = $this->refresh_api_token($refresh_token);
      if ($new_tokens && isset($new_tokens['api_token'])) {
        $api_token = $new_tokens['api_token'];
      } else {
        return null;
        PingvinLogger::log('error', 'Failed to refresh token');
      }
    }

    return $api_token;
  }



  /**
   * Refreshes the API token
   * 
   * @param string $refresh_token
   * @return array|null
   */
  public function refresh_api_token($refresh_token) {
    $bexio_general_options = get_option('pv_bexio_general_options');
    if (!$bexio_general_options) {
        PingvinLogger::log('error', 'Bexio general options not found');
        return null;
    }

    try {
        $oidc = new OpenIDConnectClient("https://auth.bexio.com/realms/bexio", $bexio_general_options['pv_bexio_client_id'], $bexio_general_options['pv_bexio_client_secret']);
        $oidc->setRedirectURL(admin_url('admin.php?page=pingvin-bexio-product-sync&auth=true'));
        $oidc->addScope(array("openid", "company_profile", "email", "offline_access", "profile", "article_show", "article_edit", "stock_edit"));
        $oidc->refreshToken($refresh_token);

        $new_api_token = $oidc->getAccessToken();
        $new_refresh_token = $oidc->getRefreshToken();
        $new_expiry_time = time() + 3600;

        if ($new_api_token) {
          update_option('pv_bexio_api_key', $new_api_token);
          update_option('pv_bexio_refresh_token', $new_refresh_token);
          update_option('pv_bexio_token_expiry', $new_expiry_time);
          PingvinLogger::log('info', 'Bexio API Token and Refresh Token refreshed.');
          return array('api_token' => $new_api_token, 'refresh_token' => $new_refresh_token);
        } else {
          PingvinLogger::log('error', 'Failed to refresh API token');
          return null;
        }
    } catch (Exception $e) {
      PingvinLogger::log('error', 'Error during token refresh: ' . $e->getMessage());
      return null;
    }
  }
}