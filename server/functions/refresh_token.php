<?php 

function createRefreshToken($pdo, $userId)
{
    // revoke all old refresh tokens
    // current one refresh per user

    $stmt = $pdo->prepare("
        UPDATE refresh_tokens
        SET revoked_at = NOW()
        WHERE user_id = ?
        AND revoked_at IS NULL;
    ");

    $stmt->execute([
        $userId,
    ]);

    // create new one
    $refreshToken = bin2hex(random_bytes(32));

    $tokenHash = hash('sha256', $refreshToken);

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + 60 * 60 * 24 * 30
    );

    $stmt = $pdo->prepare("
        INSERT INTO refresh_tokens
            (user_id, token_hash, expires_at, created_at)
        VALUES
            (?, ?, ?, NOW())
    ");

    $stmt->execute([
        $userId,
        $tokenHash,
        $expiresAt
    ]);

    return $refreshToken;
}

function verifyRefreshToken(PDO $pdo, string $refreshToken): array
{
    $tokenHash = hash('sha256', $refreshToken);

    $stmt = $pdo->prepare("
        SELECT user_id, token_hash, expires_at, revoked_at
        FROM refresh_tokens
        WHERE token_hash = ?
        LIMIT 1
    ");

    $stmt->execute([
        $tokenHash
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result === false) {
        return [
            'is_valid' => false
        ];
    }

    if ($result['revoked_at'] !== null) {
        return [
            'is_valid' => false
        ];
    }

    if (strtotime($result['expires_at']) <= time()) {
        return [
            'is_valid' => false
        ];
    }

    return [
        'is_valid' => true,
        'user_id' => (int) $result['user_id'],
        'token_hash' => $result['token_hash']
    ];
}