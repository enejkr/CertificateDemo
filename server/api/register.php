<?php

header('Content-Type: application/json');

//include
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Certificate.php';


$database = new Database();
$pdo = $database->getConnection();

$certificate = new Certificate($pdo);

$username = $_POST['username'] ?? '';

if ($username === '') {
    echo json_encode([
        'success' => false,
        'sporocilo' => 'Username manjka.'
    ]);

    exit;
}

$user = $certificate->register($username);


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