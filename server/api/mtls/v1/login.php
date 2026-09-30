<?php

require_once __DIR__ . "/../../../bootstrap.php";

$database = new Database();
$pdo = $database->getConnection();

$certificate = new Certificate($pdo);
$client = $certificate->verify();

$rateLimiter = new RateLimiter($pdo);
$rateLimit = $rateLimiter->check(
    'login:client:' . $client['id'],
    $config['login_limits']['attempts_count'],
    $config['login_limits']['time_period']
);

if (!$rateLimit['allowed']) {
    $logger -> log([
        'client_id' => $client['id'], 
        'client_name' => $client['client_name'],
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
// ////////////// IZDAJA ACCESS TOKENA \\\\\\\\\\\\\\\

$accessToken = $jwt->createAccessToken($client);

// ////////////// IZDAJA REFRESH TOKENA \\\\\\\\\\\\\\\

$refreshToken = $refreshTokenService->create($client['id']);
$logger -> log([
        'client_id' => $client['id'], 
        'client_name' => $client['client_name'],
        'action' => 'login',
        'authentication_method' => 'certificate',
        'message' => 'client succesfully logged in using certificate'
    ], 'info');

apiSuccess(
    [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        //'expires_in' => (int)$config['jwt']['expires_in'],
        
    ],
    'succesfull login'
);
