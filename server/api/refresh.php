<?php
header('Content-Type: application/json');

//included files 
require_once __DIR__ . "/../classes/RefreshToken.php";
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . "/../classes/Jwt.php";
require_once __DIR__ . "/../functions/helper.php";

$database = new Database();
$pdo = $database->getConnection();

try {

    $refreshTokenService = new RefreshToken($pdo);
    $jwt = new Jwt(
        __DIR__ . "/../keys/private.key",
        __DIR__ . "/../keys/public.key"
    );

    $refreshToken = extractToken();

    if (empty($refreshToken)) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'sporocilo' => 'Refresh token manjka.'
        ]);

        exit;
    }

    $data = $refreshTokenService->verify($refreshToken);

    if (($data['is_valid'] ?? false) !== true) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'sporocilo' => 'Refresh token ni veljaven.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $data['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'sporocilo' => 'Uporabnik ne obstaja.'
        ]);

        exit;
    }

    ////////////// IZDAJA ACCESS TOKENA \\\\\\\\\\\\\\\
    $newaccessToken = $jwt->createAccessToken($user);
    ////////////// IZDAJA ACCESS TOKENA \\\\\\\\\\\\\\\

    $newRefreshToken = $refreshTokenService->create(
        $user['id']
    );

    // invalidate old refresh token 
    $stmt = $pdo->prepare("
        UPDATE refresh_tokens
        SET revoked_at = NOW()
        WHERE token_hash = ?
    ");

    $stmt->execute([
        $data['token_hash']
    ]);

    // refreash token je poznan
    echo json_encode([
        'success' => true,
        'sporocilo' => 'Refresh uspešen.',
        'access_token' => $newaccessToken,
        'refresh_token' => $newRefreshToken,
        'token_type' => 'Bearer',
        'expires_in' => 3600
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'sporocilo' => $e->getMessage()
    ]);
}