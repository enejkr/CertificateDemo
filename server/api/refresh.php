<?php

header('Content-Type: application/json');

// included files
require_once __DIR__ . "/../classes/RefreshToken.php";
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . "/../classes/Jwt.php";
require_once __DIR__ . "/../functions/helper.php";

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $refreshTokenService = new RefreshToken($pdo);

    $jwt = new Jwt(
        __DIR__ . "/../keys/private.key",
        __DIR__ . "/../keys/public.key"
    );

    $refreshToken = extractToken();

    $data = $refreshTokenService->verify($refreshToken);

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
        'expires_in' => 20
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