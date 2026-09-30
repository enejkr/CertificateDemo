<?php
header('Content-Type: application/json');

// classes 
require_once __DIR__ . "/../../../classes/Database.php";
require_once __DIR__ . "/../../../classes/RateLimiter.php";
require_once __DIR__ . "/../../../classes/Certificate.php";
require_once __DIR__ . "/../../../classes/Jwt.php";
require_once __DIR__ . "/../../../classes/RefreshToken.php";
require_once __DIR__ . "/../../../classes/ApiException.php";

// helpers 
require_once __DIR__ . "/../../../logger.php";
require_once __DIR__ . "/../../../functions/apiHelper.php";
require_once __DIR__ . "/../../../functions/helper.php";


$config = parse_ini_file(
    __DIR__ . "/../../../config/config.ini",
    true
);

try {

    $database = new Database();
    $pdo = $database->getConnection();

    //$rateLimiter = new RateLimiter($pdo);

    $ip = $_SERVER['REMOTE_ADDR'];

    // $rateLimit = $rateLimiter->check(
    //     'refresh:ip:' . $ip,
    //     $config['general_limit']['attempts_count'],
    //     $config['general_limit']['time_period']
    // );

    // if (!$rateLimit['allowed']) {

    //     header(
    //         'Retry-After: ' . $rateLimit['retry_after']
    //     );

    //     apiError(
    //         'RATE_LIMIT_EXCEEDED',
    //         'Preveč zahtev. Poskusite ponovno čez ' .
    //             $rateLimit['retry_after'] . ' sekund.',
    //         429
    //     );
    // }

    $refreshToken = extractToken();

    $refreshTokenService = new RefreshToken(
        $pdo,
        $config['refresh_token']
    );

    $tokenData = $refreshTokenService->verify(
        $refreshToken
    );

    // $rateLimit = $rateLimiter->check(
    //     'refresh:user:' . (string)$tokenData['user_id'],
    //     $config['api_limits_user']['attempts_count'],
    //     $config['api_limits_user']['time_period']
    // );

    // if (!$rateLimit['allowed']) {

    //     header(
    //         'Retry-After: ' . $rateLimit['retry_after']
    //     );

    //     apiError(
    //         'RATE_LIMIT_EXCEEDED',
    //         'Preveč zahtev. Poskusite ponovno čez ' .
    //             $rateLimit['retry_after'] . ' sekund.',
    //         429
    //     );
    // }

    $stmt = $pdo->prepare("
        SELECT id, username
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $tokenData['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        throw new ApiException(
            'AUTHENTICATION_FAILED',
            'Prijava ni uspela.',
            401
        );
    }

    $jwt = new Jwt(
        __DIR__ . '/' . $config['keys']['private_key'],
        __DIR__ . '/' . $config['keys']['public_key'],
        $config['jwt']
    );

    $accessToken = $jwt->createAccessToken(
        $user
    );

    $newRefreshToken = $refreshTokenService->create(
        $user['id']
    );

    customLog([
        'user_id' => $user['id']
    ]);

    apiSuccess(
        [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int)$config['jwt']['expires_in']
        ],
        'Token uspešno osvežen.'
    );

} catch (ApiException $e) {

    customLog($e->getMessage());

    apiError(
        $e->getErrorCode(),
        $e->getMessage(),
        $e->getStatusCode()
    );

} catch (Throwable $e) {

    customLog($e->getMessage());

    apiError(
        'INTERNAL_SERVER_ERROR',
        'Prišlo je do notranje napake strežnika.',
        500
    );
}
