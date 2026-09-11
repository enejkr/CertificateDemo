<?php
//includes 
require_once __DIR__ . "/../classes/Jwt.php";
require_once __DIR__ . "/../functions/helper.php";
require_once __DIR__ . "/../../costume_log.php";

// keys
$publicKeyPath = __DIR__ . "/../keys/public.key";

header('Content-Type: application/json');

$accessToken = extractToken();

$jwt = new Jwt(
    __DIR__ . "/../keys/private.key",
    $publicKeyPath
);

//try catch for errors especially to catch expired jwt
try {
    $data = $jwt->verifyJwt($accessToken);
    customLog($data);
}
catch (Exception $e){
    $message = 'NAPAKA: ' . $e->getMessage();

    echo json_encode([
        'success' => false,
        'massage' => "$message",
    ]);
    
    exit();
}

// check if token valid 
echo json_encode([
    'success' => true,
    'massage' => "uspesno povezal na api",
    'access_token' => $data
]);