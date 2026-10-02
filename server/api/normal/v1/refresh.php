<?php
require_once __DIR__ . "/../../../bootstrap.php";

$database = new Database();
$pdo = $database->getConnection();

$refreshToken = extractToken();
$refreshTokenService = new RefreshToken(
    $pdo,
    $config['refresh_token']
);
$tokenData = $refreshTokenService->verify(
    $refreshToken
);

$rateLimiter = new RateLimiter($pdo);
$rateLimit = $rateLimiter->check(
    'general_api:client:' . $tokenData['client_id'],
    $config['general_limit']['attempts_count'],
    $config['general_limit']['time_period']
);

if (!$rateLimit['allowed']) {
    $logger->log([
        'client_id' => $tokenData['client_id'],
        'client_name' => $tokenData['client_name'],
        'action' => 'refresh api call',
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

$stmt = $pdo->prepare("
    SELECT id, client_name
    FROM clients
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $tokenData['client_id']
]);

$client = $stmt->fetch(PDO::FETCH_ASSOC);

if ($client === false) {

    throw new ApiException(
        'AUTHENTICATION_FAILED',
        'Authentication failed.',
        401
    );
}

$jwt = new Jwt(
    __DIR__ . '/' . $config['keys']['private_key'],
    __DIR__ . '/' . $config['keys']['public_key'],
    $config['jwt']
);

$accessToken = $jwt->createAccessToken(
    $client
);

$newRefreshToken = $refreshTokenService->create(
    $client['id']
);
$logger->log([
    'client_id' => $client['id'],
    'client_name' => $client['client_name'],
    'action' => 'refresh',
    'message' => 'access token successfully refreshed'
], 'info');
header(
    'Access-Token-Exp: ' . (int)$config['jwt']['expires_in']
);

header(
    'Refresh-Token-Exp: ' . (int)$config['refresh_token']['expires_in']
);
apiSuccess(
    [
        'access_token' => $accessToken,
        'refresh_token' => $newRefreshToken,
        'token_type' => 'Bearer',
        'expires_in' => (int)$config['jwt']['expires_in']
    ],
    'Token successfully refreshed.'
);
