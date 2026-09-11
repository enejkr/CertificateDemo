<?php
/*
function connects to a server via certificate and returens access an refreash token 
*/
function connectViaCertificate(string $url): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CAINFO => __DIR__ . '/../../certs/ca.crt',

        CURLOPT_SSLCERT => __DIR__ . '/../../certs/client/client.crt',
        CURLOPT_SSLKEY => __DIR__ . '/../../certs/client/client.key',
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new Exception('cURL napaka: ' . $error);
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

    return $data;
}