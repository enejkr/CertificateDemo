<?php

header('Content-Type: application/json');

// Composer
require_once __DIR__ . '/../../../../vendor/autoload.php';

$config = parse_ini_file(
    __DIR__ . '/../../../config/config.ini',
    true
);

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $rateLimiter = new RateLimiter($pdo);

    $ip = $_SERVER['REMOTE_ADDR'];

    $rateLimit = $rateLimiter->check(
        'login:ip:' . $ip,
        $config['login_limits']['attempts_count'],
        $config['login_limits']['time_period']
    );

    if (!$rateLimit['allowed']) {

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        apiError(
            'RATE_LIMIT_EXCEEDED',
            'Preveč zahtev. Poskusite ponovno čez ' . $rateLimit['retry_after'] . ' sekund.',
            429
        );
    }

    $certificate = new Certificate($pdo);
    
    $refreshTokenService = new RefreshToken(
        $pdo,
        $config['refresh_token']
    );

    $user = $certificate->verify();

    $jwt = new JwToken(
        __DIR__ . '/' . $config['keys']['private_key'],
        __DIR__ . '/' . $config['keys']['public_key'],
        $config['jwt']
    );

    $accessToken = $jwt->createAccessToken($user);

    ////////////// IZDAJA REFRESH TOKENA \\\\\\\\\\\\\\\

    $refreshToken = $refreshTokenService->create($user['id']);

    apiSuccess(
        [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            //'expires_in' => (int)$config['jwt']['expires_in']
        ],
        'Prijava uspešna.'
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
