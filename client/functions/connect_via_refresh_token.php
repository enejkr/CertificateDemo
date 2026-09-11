<?php 
// returns new access and new refreash tokens 
function connectViaRefreshToken(string $refrash_token, string $url): array {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $refrash_token,
            'Accept: application/json'
        ],

        CURLOPT_CAINFO => __DIR__ . '/../../certs/ca.crt',

        CURLOPT_SSLCERT => __DIR__ . '/../../certs/client/client.crt',
        CURLOPT_SSLKEY  => __DIR__ . '/../../certs/client/client.key',

        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new Exception($error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new Exception(
            'API ni vrnil veljavnega JSON-a. HTTP: ' .
            $httpCode .
            ' Response: ' .
            $response
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception(
            $data['sporocilo'] ??
            'Refresh zahteva ni uspela. HTTP: ' . $httpCode
        );
    }

    if (
        empty($data['access_token']) ||
        empty($data['refresh_token'])
    ) {
        throw new Exception(
            $data['sporocilo'] ??
            'Refresh odgovor nima tokenov.'
        );
    }

    return $data;
}