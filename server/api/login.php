<?php

header('Content-Type: application/json');

// included files 
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/certificate.php';
require_once __DIR__ . '/../functions/jwt.php';
require_once __DIR__ . '/../functions/refresh_token.php';

////////////// PREVERJANJE CERTIFIKATA \\\\\\\\\\\\\\\

$user = verifyClientCertificate($pdo);

// Certifikat ni poznan
if (!$user) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'sporocilo' => 'Client certifikat NI POZNAN.'
    ]);

    exit;
}

////////////// IZDAJA ACCESS TOKENA \\\\\\\\\\\\\\\

$accessToken = createAccessToken($user);

////////////// IZDAJA REFRESH TOKENA \\\\\\\\\\\\\\\

$refreshToken = createRefreshToken($pdo, $user['id']);

// Certifikat je poznan
echo json_encode([
    'success' => true,
    'sporocilo' => 'Prijava uspesna.',
    'access_token' => $accessToken,
    'refresh_token' => $refreshToken,
    'token_type' => 'Bearer',
    'expires_in' => 3600
]);