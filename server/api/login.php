<?php

header('Content-Type: application/json');

// included files
require_once __DIR__ . '/../classes/Certificate.php';
require_once __DIR__ . '/../classes/Jwt.php';
require_once __DIR__ . '/../classes/RefreshToken.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../../costume_log.php';

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $certificate = new Certificate($pdo);
    $refreshTokenService = new RefreshToken($pdo);

    $user = $certificate->verify();

    $jwt = new Jwt(
        __DIR__ . '/../keys/private.key',
        __DIR__ . '/../keys/public.key'
    );

    $accessToken = $jwt->createAccessToken($user);

    ////////////// IZDAJA REFRESH TOKENA \\\\\\\\\\\\\\\

    $refreshToken = $refreshTokenService->create($user['id']);

    echo json_encode([
        'success' => true,
        'sporocilo' => 'Prijava uspesna.',
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => 20
    ]);

} catch (Exception $e) {

    customLog($e->getMessage());

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'sporocilo' => 'Prijava ni uspela.'
    ]);

    exit;
}