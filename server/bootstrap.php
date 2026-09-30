<?php
// služi kot skupna začetna nastavitev, ki se bo sprožila pred logiko apijev 

header('Content-Type: application/json');

require_once __DIR__ . '/classes/ApiException.php';
require_once __DIR__ . '/functions/apiHelper.php';
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

set_error_handler(function (
    int $severity,
    string $message,
    string $file,
    int $line
): bool {
    throw new ErrorException(
        $message,
        0,
        $severity,
        $file,
        $line
    );
});

register_shutdown_function(function () use ($logger): void {

    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR
    ];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $logger->log([
        'error' => $error['message'],
        'file' => $error['file'],
        'line' => $error['line'],
    ], 'error');

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_SERVER_ERROR',
            'message' => 'Prišlo je do notranje napake strežnika.'
        ]
    ]);
});