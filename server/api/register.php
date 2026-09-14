<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Certificate.php';

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $certificate = new Certificate($pdo);

    $username = $_POST['username'] ?? '';

    if ($username === '') {
        throw new Exception('Username manjka.');
    }

    $user = $certificate->register($username);

    if (isset($user['error'])) {
        throw new Exception($user['message'] ?? 'Registracija neuspešna.');
    }

    echo json_encode([
        'success' => true,
        'sporocilo' => 'Registracija uspešna.',
        'user_id' => $user['id']
    ]);

} catch (Exception $e) {

    error_log($e->getMessage());

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'sporocilo' => $e->getMessage()
    ]);

    exit;
}