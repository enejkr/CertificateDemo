<?php

// classes 
require_once __DIR__ . "/../../../classes/Database.php";
require_once __DIR__ . "/../../../classes/RateLimiter.php";
require_once __DIR__ . "/../../../classes/Certificate.php";
require_once __DIR__ . "/../../../classes/Jwt.php";
require_once __DIR__ . "/../../../classes/RefreshToken.php";
require_once __DIR__ . "/../../../classes/ApiException.php";

// helpers 
require_once __DIR__ . "/../../../Logger.php";
require_once __DIR__ . "/../../../functions/apiHelper.php";


header('Content-Type: application/json');

$config = parse_ini_file(
    __DIR__ . '/../../../config/config.ini',
    true
);

try {
    
    $database = new Database();
    $pdo = $database->getConnection();

    $certificate = new Certificate($pdo);
    $user = $certificate->verify();

    $logger = new Logger();

    $rateLimiter = new RateLimiter($pdo);
    $rateLimit = $rateLimiter->check(
        'login:user:' . $user['id'],
        $config['login_limits']['attempts_count'],
        $config['login_limits']['time_period']
    );

    if (!$rateLimit['allowed']) {
        $logger -> log([
            'user_id' => $user['id'], 
            'username' => $user['username'],
            'action' => 'login',
            'message' => 'rate limit triggered'
        ], 'warning');

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        apiError(
            'RATE_LIMIT_EXCEEDED',
            'Preveč zahtev. Poskusite ponovno čez ' . $rateLimit['retry_after'] . ' sekund.',
            429
        );
    }

    
    $refreshTokenService = new RefreshToken(
        $pdo,
        $config['refresh_token']
    );


    $jwt = new Jwt(
        __DIR__ . '/' . $config['keys']['private_key'],
        __DIR__ . '/' . $config['keys']['public_key'],
        $config['jwt']
    );
    ////////////// IZDAJA ACCESS TOKENA \\\\\\\\\\\\\\\

    $accessToken = $jwt->createAccessToken($user);

    ////////////// IZDAJA REFRESH TOKENA \\\\\\\\\\\\\\\

    $refreshToken = $refreshTokenService->create($user['id']);
    $logger -> log([
            'user_id' => $user['id'], 
            'username' => $user['username'],
            'action' => 'login',
            'message' => 'user succesfully logged in using certificate'
        ], 'info');
        
    apiSuccess(
        [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            //'expires_in' => (int)$config['jwt']['expires_in'],
            
        ],
        'Prijava uspešna.'
    );

} catch (ApiException $e) {

    apiError(
        $e->getErrorCode(),
        $e->getMessage(),
        $e->getStatusCode()
    );

} catch (Throwable $e) {

    apiError(
        'INTERNAL_SERVER_ERROR',
        'Prišlo je do notranje napake strežnika.',
        500
    );
}
