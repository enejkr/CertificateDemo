<?php

// composer 
require_once __DIR__ . "/../../vendor/autoload.php";

//classes 
require_once __DIR__ . "/../classes/JwtToken.php";

// helpers
require_once __DIR__ . "/../functions/helper.php";
require_once __DIR__ . "/../../costume_log.php";



$config = parse_ini_file(
    __DIR__ . "/../config/config.ini",
    true
);

header('Content-Type: application/json');

try {
    $accessToken = extractToken();

    $jwt = new JwToken(
        __DIR__ . "/../keys/private.key",
        $config['keys']['public_key'],
        $config['jwt']
    );

    $data = $jwt->verifyJwt($accessToken);

    customLog($data);

    echo json_encode([
        'success' => true,
        'message' => 'Uspešno povezan na API',
        'access_token' => $data
    ]);

} catch (Exception $e) {

    customLog($e->getMessage());

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'sporocilo' => $e->getMessage()
    ]);

    exit;
}