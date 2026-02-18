<?php
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

function pv_api_call($method, $endpoint, $payload = null) {
  $auth = Pingvin_Bexio_ProductSync_Auth::get_instance();

  $api_token = $auth->get_valid_api_token();
  $url = 'https://api.bexio.com/' . ltrim($endpoint, '/');
  $headers = [
    'Accept: application/json',
    "Authorization: Bearer ".$api_token,
  ];

  if ( $method === 'POST' && $payload !== null ) {
    $headers[] = 'Content-Type: application/json';
  }
  
  ob_start();  
  $out = fopen('php://output', 'w');

  $ch = curl_init();

  if ($method == 'POST') curl_setopt($ch, CURLOPT_POST, true);
  if ($method == 'POST' && $payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  if ($method == 'DELETE') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_VERBOSE, 1);
  //curl_setopt($ch, CURLOPT_STDERR, $out);
  curl_setopt($ch, CURLINFO_HEADER_OUT, 1);



  $output = [];
  $output['result'] = json_decode(curl_exec($ch));
  $output['info'] = curl_getinfo($ch);
  $output['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);
  fclose($out);
  $output['debug'] = ob_get_clean();

  return $output;
}

?>