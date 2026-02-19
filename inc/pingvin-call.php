<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

function pv_api_call( string $method, string $endpoint, ?string $payload = null ): array {
  $auth      = Pingvin_Bexio_ProductSync_Auth::get_instance();
  $api_token = $auth->get_valid_api_token();
  $url       = 'https://api.bexio.com/' . ltrim( $endpoint, '/' );

  $headers = [
    'Accept'        => 'application/json',
    'Authorization' => 'Bearer ' . $api_token,
  ];

  $args = [
    'method'      => $method,
    'headers'     => $headers,
    'timeout'     => 30,
    'redirection' => 5,
    'sslverify'   => true,
  ];

  if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) && $payload !== null ) {
    $args['headers']['Content-Type'] = 'application/json';
    $args['body'] = $payload;
  }

  $response = wp_remote_request( $url, $args );

  if ( is_wp_error( $response ) ) {
    return [
      'result' => null,
      'status' => 0,
      'error'  => $response->get_error_message(),
    ];
  }

  return [
    'result' => json_decode( wp_remote_retrieve_body( $response ) ),
    'status' => (int) wp_remote_retrieve_response_code( $response ),
  ];
}

?>
