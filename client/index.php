<?php

require_once __DIR__ . '/classes/Client.php';
require_once __DIR__ . '/functions/token_helper.php';

$url = 'https://localhost:8443/api/login.php';
$apiUrl = 'https://localhost:8443/api/api.php';
$refreshUrl = 'https://localhost:8443/api/refresh.php';
$registerUrl = 'https://localhost:8443/api/register.php';

$client = new Client(
    dirname(__DIR__) . '/certs/ca.crt',
    dirname(__DIR__) . '/certs/client/client.crt',
    dirname(__DIR__) . '/certs/client/client.key'
);

$message = '';

function saveTokens(
    string $accessToken,
    string $refreshToken
): void {
    $accessExp = getExpFromToken($accessToken);

    if ($accessExp === null) {
        throw new Exception(
            'Access token nima veljavnega expiration časa.'
        );
    }

    $refreshExp = time() + (60 * 60 * 24 * 30);

    tokenToCoockie(
        'access_token',
        $accessToken,
        $accessExp
    );

    tokenToCoockie(
        'refresh_token',
        $refreshToken,
        $refreshExp
    );
}

function refreshTokens(
    Client $client,
    string $refreshUrl,
    string $refreshToken
): array {
    if (empty($refreshToken)) {
        throw new Exception('Refresh Token manjka.');
    }

    $result = $client->connectViaRefreshToken(
        $refreshToken,
        $refreshUrl
    );

    saveTokens(
        $result['access_token'],
        $result['refresh_token']
    );

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        //certificate login
        if (isset($_POST['connect'])) {

            //gain access and refresh tokens
            $result = $client->connectViaCertificate($url);

            //tokens
            saveTokens(
                $result['access_token'],
                $result['refresh_token']
            );

            $message = 'Uspesna povezava.';
        }

        // access and refresh token api usage
        if (isset($_POST['access_token'])) {

            $accessToken = $_COOKIE['access_token'] ?? null;
            $refreshToken = $_COOKIE['refresh_token'] ?? null;

            if (empty($accessToken)) {

                if (empty($refreshToken)) {
                    throw new Exception('bouth Token missing');
                }

                $result = refreshTokens(
                    $client,
                    $refreshUrl,
                    $refreshToken
                );

                $accessToken = $result['access_token'];
                $refreshToken = $result['refresh_token'];
            }

            $response = $client->connectViaAccessToken(
                $accessToken,
                $apiUrl
            );
            

            $message = json_encode(
                $response,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }

        if (isset($_POST['register'])) {

            $username = trim($_POST['username'] ?? '');

            if ($username === '') {
                throw new Exception('Username manjka.');
            }

            $response = $client->connectToRegister(
                $registerUrl,
                $username
            );

            $message = json_encode(
                $response,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }

    } catch (Throwable $e) {
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
                name="username"
                id="username"
            >

            <button
                type="submit"
                name="register"
                onclick="return registerUser()"
            >
                register
            </button>

        </form>

        <pre><?php echo htmlspecialchars($message); ?></pre>

        <br>

        <p>
            trenuten cas: <?php echo time(); ?>
        </p>

        <script>
            function registerUser() {
                const username = prompt('Vnesi username:');

                if (
                    username === null ||
                    username.trim() === ''
                ) {
                    return false;
                }

                document.getElementById('username').value =
                    username.trim();

                return true;
            }
        </script>

    </body>

</html>
