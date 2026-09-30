<?php

// composer 
require_once __DIR__ . '/../../../../vendor/autoload.php';
// helpers
require_once __DIR__ . "/../../../functions/helper.php";

$config = parse_ini_file(
    __DIR__ . "/../../../config/config.ini",
    true
);

header('Content-Type: application/json');

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $rateLimiter = new RateLimiter($pdo);

    $ip = $_SERVER['REMOTE_ADDR'];

    $rateLimit = $rateLimiter->check(
        'api:ip:' . $ip,
        $config['api_limits_ip']['attempts_count'],
        $config['api_limits_ip']['time_period']
 
    );

    if (!$rateLimit['allowed']) {

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        apiError(
            'RATE_LIMIT_EXCEEDED',
            'Preveč zahtev. Poskusite ponovno čez ' .
                $rateLimit['retry_after'] . ' sekund.',
            429
        );
    }

    $accessToken = extractToken();

    $jwt = new JwToken(
        $config['keys']['private_key'],
        $config['keys']['public_key'],
        $config['jwt']
    );

    $data = $jwt->verifyJwt($accessToken);

    $rateLimit = $rateLimiter->check(
        'api:user:' . (string)$data['sub'],
        $config['api_limits_user']['attempts_count'],
        $config['api_limits_user']['time_period']
    );

    if (!$rateLimit['allowed']) {

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        apiError(
            'RATE_LIMIT_EXCEEDED',
            'Preveč zahtev. Poskusite ponovno čez ' .
                $rateLimit['retry_after'] . ' sekund.',
            429
        );
    }

    customLog($data);

    apiSuccess(
        [
            'access_token' => $accessToken
        ],
        'Uspešno povezan na API'
    );

} catch (ApiException $e) {

    customLog($e->getMessage());

    apiError(
        $e->getErrorCode(),
        $e->getMessage(),
        $e->getStatusCode()
    );

} catch (Throwable $e) {

    customLog($e->getMessage());

    apiError(
        'INTERNAL_SERVER_ERROR',
        'Prišlo je do notranje napake strežnika.',
        500
    );
}
