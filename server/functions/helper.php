<?php

function extractToken(): string
{
    $headers = getallheaders();

    $authorization = $headers['Authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        throw new ApiException(
            'INVALID_ACCESS_TOKEN',
            'Access token manjka ali je neveljaven.',
            401
        );
    }

    return trim($matches[1]);
}

