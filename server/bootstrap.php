<?php
// služi kot skupna začetna nastavitev, ki se bo sprožila pred logiko apijev 
// prav tako deluje kot centralni server error handaler 
header('Content-Type: application/json');

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/functions/apiHelper.php';
require_once __DIR__ . '/functions/helper.php';
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

$config = parse_ini_file(
    __DIR__ . '/config/config.ini',
    true
);

// ob kakoršni koli php napaki sproži exeption ki ga polovi set_exception_handler
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
            'message' => 'INTERNAL_SERVER_ERROR'
        ]
    ]);
});

set_exception_handler(function (Throwable $e) use ($logger): void {

    if ($e instanceof ApiException) {

        $logger->log([
            'error_code' => $e->getErrorCode(),
            'message' => $e->getMessage(),
            'status_code' => $e->getStatusCode(),
        ], 'error');

        apiError(
            $e->getErrorCode(),
            $e->getMessage(),
            $e->getStatusCode()
        );

        return;
    }

    $logger->log([
        'error_code' => 'INTERNAL_SERVER_ERROR',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], 'error');

    apiError(
        'INTERNAL_SERVER_ERROR',
        'INTERNAL_SERVER_ERROR',
        500
    );
});
