<?php
require_once __DIR__ . "/../../../bootstrap.php";



$database = new Database();
$pdo = $database->getConnection();

$accessToken = extractToken();

$jwt = new Jwt(
    $config['keys']['private_key'],
    $config['keys']['public_key'],
    $config['jwt']
);
$data = $jwt->verifyJwt($accessToken);


$rateLimiter = new RateLimiter($pdo);
$rateLimit = $rateLimiter->check(
    'general_api:client:' . $data['client_id'],
    $config['general_limit']['attempts_count'],
    $config['general_limit']['time_period']
);

if (!$rateLimit['allowed']) {
    $logger -> log([
        'client_id' => $data['client_id'], 
        'client_name' => $data['client_name'],
        'action' => 'api call',
        'message' => 'rate limit triggered'
    ], 'warning');

    header(
        'Retry-After: ' . $rateLimit['retry_after']
    );

    throw new ApiException(
        'RATE_LIMIT_EXCEEDED',
        'RATE_LIMIT_EXCEEDED',
        429
    );
}

$logger -> log([
        'client_id' => $data['client_id'], 
        'client_name' => $data['client_name'],
        'action' => 'api call',
        'message' => 'success'
    ], 'info');

apiSuccess(
    [
        'access_token' => $accessToken
    ],
    'Successful connection'
);