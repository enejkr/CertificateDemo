<?php

function extractToken (){

    $headers = getallheaders();

    $authorization = $headers['Authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'sporocilo' => 'access token manjka ali je neveljaven.'
        ]);

        exit;
    }

    return $matches[1];
}