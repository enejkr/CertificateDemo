<?php
// returns exp of a token
function getExpFromToken($token){
    $jwtParts = explode('.', $token);

    if (count($jwtParts) !== 3) {
        return null;
    }

    $jsonData = $jwtParts[1];

    $jsonData .= str_repeat(
        '=',
        (4 - strlen($jsonData) % 4) % 4
    );

    $jsonData = strtr($jsonData, '-_', '+/');

    $decoded = base64_decode($jsonData, true);

    if ($decoded === false) {
        return null;
    }

    $data = json_decode($decoded, true);

    if (
        !is_array($data) ||
        !isset($data['exp']) ||
        !is_numeric($data['exp'])
    ) {
        return null;
    }

    return (int)$data['exp'];
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
function isTokenExpired(string $token): bool
{
    $exp = getExpFromToken($token);

    if ($exp === null) {
        return true;
    }

    return time() >= $exp;
}

function saveTokens(
    string $accessToken,
    string $refreshToken
): void {

    $accessExp = getExpFromToken($accessToken);

    if ($accessExp === null) {
        throw new Exception(
            'Access token nima veljavnega expiration časa.'
        );
    }

    $refreshExp = time() + (60 * 60 * 24 * 30);

    tokenToCoockie(
        'access_token',
        $accessToken,
        $accessExp
    );

    tokenToCoockie(
        'refresh_token',
        $refreshToken,
        $refreshExp
    );
}

function refreshTokens(
    Client $client,
    string $refreshUrl,
    string $refreshToken
): array {
    if (empty($refreshToken)) {
        throw new Exception('Refresh Token manjka.');
    }

    $result = $client->connectViaRefreshToken(
        $refreshToken,
        $refreshUrl
    );

    saveTokens(
        $result['access_token'],
        $result['refresh_token']
    );

    return $result;
}

?>