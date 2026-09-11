<?php
/*
function connects to a server via certificate and returens access an refreash token 
*/
function connectToRegister(string $url, string $username): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => http_build_query([
            'username' => $username
        ]),

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

    if ($data === null) {
        throw new Exception(
            'API ni vrnil veljavnega JSON-a. ' .
            'HTTP: ' . $httpCode .
            ' | Odgovor: ' . $response
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception(
            $data['sporocilo']
            ?? $data['message']
            ?? 'API napaka. HTTP status: ' . $httpCode
        );
    }

    return $data;
}