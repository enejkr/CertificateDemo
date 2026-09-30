<?php
require_once __DIR__ . "/../../../bootstrap.php";

try {

    // classes 
    require_once __DIR__ . "/../../../classes/Database.php";
    require_once __DIR__ . "/../../../classes/RateLimiter.php";
    require_once __DIR__ . "/../../../classes/Certificate.php";
    require_once __DIR__ . "/../../../classes/Jwt.php";
    require_once __DIR__ . "/../../../classes/RefreshToken.php";
    
    // herlpers 
    require_once __DIR__ . "/../../../functions/helper.php";

    $config = parse_ini_file(
        __DIR__ . '/../../../config/config.ini',
        true
    );

    $database = new Database();
    $pdo = $database->getConnection();

    $rateLimiter = new RateLimiter($pdo);

    $accessToken = extractToken();

    $jwt = new Jwt(
        $config['keys']['private_key'],
        $config['keys']['public_key'],
        $config['jwt']
    );
    $data = $jwt->verifyJwt($accessToken);
    $logger -> log([
        'data' => $data
    ], 'debug');


    $rateLimit = $rateLimiter->check(
        'api:user:' . $data['sub'],
        $config['general_limit']['attempts_count'],
        $config['general_limit']['time_period']
    );

    if (!$rateLimit['allowed']) {
       $logger -> log([
            'user_id' => $data['sub'], 
            'username' => $data['username'],
            'action' => 'api call',
            'message' => 'rate limit triggered'
        ], 'warning');

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        apiError(
            'RATE_LIMIT_EXCEEDED',
            'RATE_LIMIT_EXCEEDED',
            429
        );
    }

    $logger -> log([
            'user_id' => $data['sub'], 
            'username' => $data['username'],
            'action' => 'api call',
            'message' => 'success'
        ], 'info');

    apiSuccess(
        [
            'access_token' => $accessToken
        ],
        'sucessful connection'
    );

} catch (ApiException $e) {
    $logger -> log([
            'error_code' => $e->getErrorCode(), 
            'message' => $e->getMessage(),
            'status_code' => $e->getStatusCode(),
        ], 'error');

    apiError(
        $e->getErrorCode(),
        $e->getMessage(),
        $e->getStatusCode()
    );

} catch (Throwable $e) {

    $logger -> log([
            'error_code' => 'INTERNAL_SERVER_ERROR', 
            'message' => $e->getMessage(),
            'status_code' => $e->getStatusCode(),
        ], 'error');
    
    apiError(
        'INTERNAL_SERVER_ERROR',
        'INTERNAL_SERVER_ERROR',
        500
    );
}
