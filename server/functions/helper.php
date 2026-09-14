<?php

function extractToken()
{
    $headers = getallheaders();

    $authorization = $headers['Authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        throw new Exception("Access token manjka ali je neveljaven.");
    }

    return $matches[1];
}