<?php
// returns exp of a token
function getExpFromToken($token){
    $jwtParts = explode('.', $token);
    $jsonData = $jwtParts[1];
    $jsonData .= str_repeat(
        '=',
        (4 - strlen($jsonData) % 4) % 4
    );

    $jsonData = strtr($jsonData, '-_', '+/');

    $data = json_decode(
        base64_decode($jsonData),
        true
    );

    return $data['exp'];
}

//creates a coockie form a token 
function tokenToCoockie(string $name, string $token, int $exp){
    setcookie($name, $token, [
        'expires' => $exp,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

?>