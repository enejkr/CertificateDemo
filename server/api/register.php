<?php

header('Content-Type: application/json');

//include
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/certificate.php';
require_once __DIR__ . '/../functions/jwt.php';
require_once __DIR__ . '/../functions/refresh_token.php';
require_once __DIR__ . "/../functions/log.php";

$username = $_POST['username'] ?? '';

if ($username === '') {
    echo json_encode([
        'success' => false,
        'sporocilo' => 'Username manjka.'
    ]);

    exit;
}

$user = register($pdo, $username);

costumeLog($user);

// Registracija ni uspela
if ($user === false) {
    echo json_encode([
        'success' => false,
        'sporocilo' => 'Registracija neuspesna.'
    ]);

    exit;
}

// Napaka
if (isset($user['error'])) {
    echo json_encode([
        'success' => false,
        'sporocilo' => $user['message'] ?? 'Registracija neuspesna.',
        'error' => $user['error']
    ]);

    exit;
}

// Uspešna registracija
echo json_encode([
    'success' => true,
    'sporocilo' => 'Registracija uspesna.',
    'user_id' => $user['id']
]);

exit;