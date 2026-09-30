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
    'general_api:user:' . $tokenData['user_id'],
    $config['general_limit']['attempts_count'],
    $config['general_limit']['time_period']
);

if (!$rateLimit['allowed']) {
    $logger -> log([
        'user_id' => $tokenData['sub'], 
        'username' => $tokenData['username'],
        'action' => 'refresh api call',
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

$stmt = $pdo->prepare("
    SELECT id, username
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $tokenData['user_id']
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    
    throw new ApiException(
        'AUTHENTICATION_FAILED',
        'Prijava ni uspela.',
        401
    );
}

$jwt = new Jwt(
    __DIR__ . '/' . $config['keys']['private_key'],
    __DIR__ . '/' . $config['keys']['public_key'],
    $config['jwt']
);

$accessToken = $jwt->createAccessToken(
    $user
);

$newRefreshToken = $refreshTokenService->create(
    $user['id']
);

apiSuccess(
    [
        'access_token' => $accessToken,
        'refresh_token' => $newRefreshToken,
        'token_type' => 'Bearer',
        'expires_in' => (int)$config['jwt']['expires_in']
    ],
    'Token uspešno osvežen.'
);
