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

    public function create($clientId)
    {
        // Revoke all old refresh tokens
        // Current one refresh token per client
        $stmt = $this->pdo->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE client_id = ?
            AND revoked_at IS NULL;
        ");

        $stmt->execute([
            $clientId,
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
                (client_id, token_hash, expires_at, created_at)
            VALUES
                (?, ?, ?, NOW())
        ");

        $stmt->execute([
            $clientId,
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
            SELECT
                rt.client_id,
                rt.expires_at,
                rt.revoked_at,
                c.client_name
            FROM refresh_tokens rt
            INNER JOIN clients c ON c.id = rt.client_id
            WHERE rt.token_hash = ?
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
                'Refresh token is invalid.',
                401
            );
        }

        // Is revoked
        if ($result['revoked_at'] !== null) {
            throw new ApiException(
                'INVALID_REFRESH_TOKEN',
                'Refresh token is invalid.',
                401
            );
        }

        // Is expired
        $expiresAt = strtotime(
            $result['expires_at']
        );

        if ($expiresAt === false) {
            throw new RuntimeException(
                'Invalid refresh token expiration date.'
            );
        }

        if ($expiresAt <= time()) {
            throw new ApiException(
                'REFRESH_TOKEN_EXPIRED',
                'Refresh token has expired.',
                401
            );
        }

        return [
            'is_valid' => true,
            'client_id' => (int)$result['client_id'],
            'client_name' => $result['client_name']
        ];
    }
}
