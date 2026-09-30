<?php

class RefreshToken
{
    private PDO $pdo;
    private int $expiresIn;
    private int $tokenBytes;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;

        $this->expiresIn = (int)$config['expires_in'];
        $this->tokenBytes = (int)$config['token_bytes'];
    }

    public function create($userId)
    {
        // Revoke all old refresh tokens
        // Current one refresh token per user
        $stmt = $this->pdo->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE user_id = ?
            AND revoked_at IS NULL;
        ");

        $stmt->execute([
            $userId,
        ]);

        // Create new refresh token
        $refreshToken = bin2hex(
            random_bytes($this->tokenBytes)
        );

        $tokenHash = hash(
            'sha256',
            $refreshToken
        );

        // Calculate expiration date
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + $this->expiresIn
        );

        $stmt = $this->pdo->prepare("
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

    public function verify(string $refreshToken): array
    {
        $tokenHash = hash(
            'sha256',
            $refreshToken
        );

        $stmt = $this->pdo->prepare("
            SELECT user_id, token_hash, expires_at, revoked_at
            FROM refresh_tokens
            WHERE token_hash = ?
            LIMIT 1
        ");

        $stmt->execute([
            $tokenHash
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Is not in DB
        if ($result === false) {
            throw new ApiException(
                'INVALID_REFRESH_TOKEN',
                'Refresh token ni veljaven.',
                401
            );
        }

        // Is revoked
        if ($result['revoked_at'] !== null) {
            throw new ApiException(
                'INVALID_REFRESH_TOKEN',
                'Refresh token ni veljaven.',
                401
            );
        }

        // Is expired
        $expiresAt = strtotime(
            $result['expires_at']
        );

        if ($expiresAt === false) {
            throw new RuntimeException(
                'Invalid refresh token expiration date'
            );
        }

        if ($expiresAt <= time()) {
            throw new ApiException(
                'REFRESH_TOKEN_EXPIRED',
                'Refresh token je potekel.',
                401
            );
        }

        return [
            'is_valid' => true,
            'user_id' => (int)$result['user_id'],
            'token_hash' => $result['token_hash']
        ];
    }
}
