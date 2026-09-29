<?php

// composer 
require_once __DIR__ . '/../../../../vendor/autoload.php';
// helpers
require_once __DIR__ . "/../../../functions/helper.php";

$config = parse_ini_file(
    __DIR__ . "/../../../config/config.ini",
    true
);

header('Content-Type: application/json');

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $rateLimiter = new RateLimiter($pdo);

    $ip = $_SERVER['REMOTE_ADDR'];

    $rateLimit = $rateLimiter->check(
        'api:ip:' . $ip,
        $config['api_limits_ip']['attempts_count'],
        $config['api_limits_ip']['time_period']
 
    );

    if (!$rateLimit['allowed']) {
        http_response_code(429);

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        echo json_encode([
            'success' => false,
            'sporocilo' => 'Prevec zahtev. Poskusite ponovno cez ' .
                $rateLimit['retry_after'] . ' sekund.'
        ]);

        exit;
    }

    $accessToken = extractToken();

    $jwt = new JwToken(
        $config['keys']['private_key'],
        $config['keys']['public_key'],
        $config['jwt']
    );

    $data = $jwt->verifyJwt($accessToken);

    $rateLimit = $rateLimiter->check(
        'api:user:' . (string)$data['sub'],
        $config['api_limits_user']['attempts_count'],
        $config['api_limits_user']['time_period']
    );

    if (!$rateLimit['allowed']) {
        http_response_code(429);

        header(
            'Retry-After: ' . $rateLimit['retry_after']
        );

        echo json_encode([
            'success' => false,
            'sporocilo' => 'Prevec zahtev. Poskusite ponovno cez ' . $rateLimit['retry_after'] . ' sekund.',
        ]);

        exit;
    }

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
