<?php

require_once __DIR__ . "/../classes/Jwt.php";
require_once __DIR__ . "/../functions/helper.php";
require_once __DIR__ . "/../../costume_log.php";

$publicKeyPath = __DIR__ . "/../keys/public.key";

header('Content-Type: application/json');

try {
    $accessToken = extractToken();

    $jwt = new Jwt(
        __DIR__ . "/../keys/private.key",
        $publicKeyPath
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
        'sporocilo' => 'Neveljaven access token.'
    ]);

    exit;
}