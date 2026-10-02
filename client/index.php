<?php

require_once __DIR__ . '/classes/Client.php';
require_once __DIR__ . '/functions/token_helper.php';

$url = 'https://localhost:8443/auth/login';
$apiUrl = 'https://localhost:8443/api/v1/api';
$refreshUrl = 'https://localhost:8443/api/v1/refresh';

$client = new Client(
    dirname(__DIR__) . '/certs/ca.crt',
    dirname(__DIR__) . '/certs/Enej1/client.crt',
    dirname(__DIR__) . '/certs/Enej1/client.key'
);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        //certificate login
        if (isset($_POST['connect'])) {

            //gain access and refresh tokens
            $result = $client->connectViaCertificate($url);

            //tokens
            saveTokens(
                $result['data']['access_token'],
                $result['data']['refresh_token']
            );

            $message =
                "LOGIN USPEŠEN\n\n" .

                "HTTP STATUS:\n" .
                $result['http_code'] . "\n\n" .

                "REQUEST HEADERS:\n" .
                ($result['request_headers'] ?: '(ni podatka)') . "\n\n" .

                "RESPONSE HEADERS:\n" .
                $result['headers'] . "\n\n" .

                "RESPONSE BODY:\n" .
                $result['body'];
        }

        if (isset($_POST['access_token'])) {

            $accessToken = $_COOKIE['access_token'] ?? null;
            $refreshToken = $_COOKIE['refresh_token'] ?? null;

            $refreshResponse = null;
            $apiResponse = null;

            /*
     * ACCESS TOKEN MANJKA ALI JE POTEKEL
     */
            if (
                empty($accessToken) ||
                isTokenExpired($accessToken)
            ) {

                if (empty($refreshToken)) {
                    throw new Exception(
                        'Refresh token manjka.'
                    );
                }

                /*
         * Kličemo:
         *
         * /api/v1/refresh
         */
                $refreshResponse =
                    $client->connectViaRefreshToken(
                        $refreshToken,
                        $refreshUrl
                    );

                /*
         * Dobimo NOVA tokena.
         */
                $accessToken =
                    $refreshResponse['data']['access_token'];

                $refreshToken =
                    $refreshResponse['data']['refresh_token'];

                /*
         * Pomembno:
         * shrani tudi NOV refresh token.
         */
                saveTokens(
                    $accessToken,
                    $refreshToken
                );
            }

            /*
     * Zdaj pokličemo dejanski API:
     *
     * /api/v1/api
     */
            $apiResponse =
                $client->connectViaAccessToken(
                    $accessToken,
                    $apiUrl
                );

            /*
     * Prikaži oba requesta.
     */
            $output = [];

            /*
     * REFRESH REQUEST
     */
            if ($refreshResponse !== null) {

                $output['refresh_request'] = [
                    'endpoint' =>
                    $refreshResponse['debug']['endpoint'],

                    'http_code' =>
                    $refreshResponse['http_code'],

                    'request_headers' =>
                    $refreshResponse['request_headers'],

                    'response_headers' =>
                    $refreshResponse['headers'],

                    'parsed_headers' =>
                    $refreshResponse['parsed_headers'],

                    'access_token_exp' =>
                    $refreshResponse['access_token_exp'],

                    'refresh_token_exp' =>
                    $refreshResponse['refresh_token_exp'],

                    'response_body' =>
                    $refreshResponse['body'],
                ];
            }

            /*
     * API REQUEST
     */
            $output['api_request'] = [
                'endpoint' =>
                $apiResponse['debug']['endpoint'],

                'http_code' =>
                $apiResponse['http_code'],

                'request_headers' =>
                $apiResponse['request_headers'],

                'response_headers' =>
                $apiResponse['headers'],

                'parsed_headers' =>
                $apiResponse['parsed_headers'],

                'access_token_exp' =>
                $apiResponse['access_token_exp'],

                'refresh_token_exp' =>
                $apiResponse['refresh_token_exp'],

                'response_body' =>
                $apiResponse['body'],
            ];

            $message = json_encode(
                $output,
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE
            );
        }
    } catch (Exception $e) {
        $message = 'NAPAKA: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Client</title>
</head>

<body>

    <h1>Client</h1>

    <form method="post" id="clientForm">

        <button type="submit" name="connect">
            Poveži se
        </button>

        <button type="submit" name="access_token">
            Poveži se z Access Tokenom
        </button>

        <input
            type="hidden"
            name="client_name"
            id="client_name">

    </form>

    <pre><?php echo htmlspecialchars($message); ?></pre>

    <br>

    <p>
        trenuten cas: <?php echo time(); ?>
    </p>

</body>

</html>