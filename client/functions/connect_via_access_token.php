<?php
/*
fuction connects to a api using access token 
*/
function connectViaAccessToken (string $accessToken, string $url):array {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ],

        CURLOPT_CAINFO => __DIR__ . '/../../certs/ca.crt',

        CURLOPT_SSLCERT => __DIR__ . '/../../certs/client/client.crt',
        CURLOPT_SSLKEY  => __DIR__ . '/../../certs/client/client.key',
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new Exception('API ni vrnil veljavnega JSON-a: ' . $response);
    }

    return $data;

}