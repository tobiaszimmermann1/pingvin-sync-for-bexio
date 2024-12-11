<?php

function pv_api_call($method, $endpoint) {

  $url = rtrim(get_option('loonity_connector_options')['loonity_api_url'], '/') . '/' . ltrim($endpoint, '/');
  $headers = [
    "Authorization: Token ".get_option('loonity_connector_options')['loonity_api_key_0'],
  ];
  
  ob_start();  
  $out = fopen('php://output', 'w');

  $ch = curl_init();


  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_VERBOSE, 1);
  //curl_setopt($ch, CURLOPT_STDERR, $out);
  curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

  if ($method == 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
  }

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