<?php

header('Content-Type: application/json');

// helpers 
require_once __DIR__ . "/../../../functions/helper.php";

// composer 
require_once __DIR__ . '/../../../../vendor/autoload.php';

$config = parse_ini_file(
    __DIR__ . '/../../../config/config.ini',
    true
);

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $rateLimiter = new RateLimiter($pdo);

    $refreshTokenService = new RefreshToken(
        $pdo,
        $config['refresh_token']
    );

    $jwt = new JwToken(
        $config['keys']['private_key'],
        $config['keys']['public_key'],
        $config['jwt']
    );

    $refreshToken = extractToken();

    $data = $refreshTokenService->verify($refreshToken);

    $rateLimit = $rateLimiter->check(
        'refresh:user:' . $data['user_id'],
        $config['refresh_limits']['attempts_count'],
        $config['refresh_limits']['time_period']
    );

    if (!$rateLimit['allowed']) {
        http_response_code(429);

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        echo json_encode([
            'success' => false,
            'sporocilo' => 'Prevec zahtev. Poskusite ponovno cez ' . $rateLimit['retry_after'] . ' sekund.',
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $data['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        throw new Exception('Uporabnik ne obstaja.');
    }

    ////////////// IZDAJA ACCESS TOKENA \\\\\\\\\\\\\\\

    $newAccessToken = $jwt->createAccessToken($user);

    ////////////// IZDAJA NOVEGA REFRESH TOKENA \\\\\\\\\\\\\\\

    $newRefreshToken = $refreshTokenService->create(
        $user['id']
    );

    echo json_encode([
        'success' => true,
        'sporocilo' => 'Refresh uspešen.',
        'access_token' => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'token_type' => 'Bearer',
        'expires_in' => (int)$config['jwt']['expires_in']
    ]);

} catch (Exception $e) {

    error_log($e->getMessage());

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'sporocilo' => 'Refresh token ni veljaven.'
    ]);

    exit;
}