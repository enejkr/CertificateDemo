<?php
require_once __DIR__ . "/../../../bootstrap.php";



$database = new Database();
$pdo = $database->getConnection();

$rateLimiter = new RateLimiter($pdo);

$accessToken = extractToken();

$jwt = new Jwt(
    $config['keys']['private_key'],
    $config['keys']['public_key'],
    $config['jwt']
);
$data = $jwt->verifyJwt($accessToken);
$logger -> log([
    'data' => $data
], 'debug');


$rateLimit = $rateLimiter->check(
    'general_api:user:' . $data['sub'],
    $config['general_limit']['attempts_count'],
    $config['general_limit']['time_period']
);

if (!$rateLimit['allowed']) {
    $logger -> log([
        'user_id' => $data['sub'], 
        'username' => $data['username'],
        'action' => 'api call',
        'message' => 'rate limit triggered'
    ], 'warning');

    header(
        'Retry-After: ' . $rateLimit['retry_after']
    );

    apiError(
        'RATE_LIMIT_EXCEEDED',
        'RATE_LIMIT_EXCEEDED',
        429
    );
}

$logger -> log([
        'user_id' => $data['sub'], 
        'username' => $data['username'],
        'action' => 'api call',
        'message' => 'success'
    ], 'info');

apiSuccess(
    [
        'access_token' => $accessToken
    ],
    'sucessful connection'
);

