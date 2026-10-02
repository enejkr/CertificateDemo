<?php

function apiSuccess(array $data = [], string $message = 'OK', int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

// old 
function apiError(
    string $errorCode,
    string $message,
    int $statusCode
): never {
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'message' => $message
        ]
    ]);

    exit;
}