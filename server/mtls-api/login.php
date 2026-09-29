<?php

header('Content-Type: application/json');

// Composer
require_once __DIR__ . '/../../vendor/autoload.php';

// Classes
require_once __DIR__ . '/../classes/Certificate.php';
require_once __DIR__ . '/../classes/JwtToken.php';
require_once __DIR__ . '/../classes/RefreshToken.php';
require_once __DIR__ . '/../classes/Database.php';

// Logger
require_once __DIR__ . '/../../costume_log.php';

$config = parse_ini_file(
    __DIR__ . '/../config/config.ini',
    true
);

try {

    $database = new Database();
    $pdo = $database->getConnection();

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

    echo json_encode([
        'success' => true,
        'sporocilo' => 'Prijava uspesna.',
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => (int)$config['jwt']['expires_in']
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