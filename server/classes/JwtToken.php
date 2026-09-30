<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwToken
{
    private string $privateKeyPath;
    private string $publicKeyPath;
    private string $algorithm;
    private int $expiresIn;

    public function __construct(
        string $privateKeyPath,
        string $publicKeyPath,
        array $config
    ) {
        $this->privateKeyPath = $privateKeyPath;
        $this->publicKeyPath = $publicKeyPath;

        $this->algorithm = $config['algorithm'];
        $this->expiresIn = (int)$config['expires_in'];
    }

    /**
     * Ustvari access JWT za uporabnika.
     */
    public function createAccessToken($user): string
    {
        $privateKey = file_get_contents($this->privateKeyPath);

        if ($privateKey === false) {
            throw new RuntimeException(
                'Private key ni mogoče prebrati.'
            );
        }

        $now = time();

        $payload = [
            'sub' => (string)$user['id'],
            'username' => $user['username'],
            'iat' => $now,
            'exp' => $now + $this->expiresIn
        ];

        return JWT::encode(
            $payload,
            $privateKey,
            $this->algorithm
        );
    }

    /**
     * Preveri in dekodira JWT.
     */
    public function verifyJwt(string $token): array
    {
        $publicKey = file_get_contents($this->publicKeyPath);

        if ($publicKey === false) {
            throw new RuntimeException(
                'Public key ni mogoče prebrati.'
            );
        }

        try {
            $payload = JWT::decode(
                $token,
                new Key(
                    $publicKey,
                    $this->algorithm
                )
            );
        } catch (Exception $e) {
            throw new ApiException(
                'INVALID_ACCESS_TOKEN',
                'Access token ni veljaven.',
                401
            );
        }

        $payload = (array)$payload;

        // Preveri, ali payload vsebuje obvezne podatke
        if (
            !isset(
                $payload['sub'],
                $payload['username'],
                $payload['iat'],
                $payload['exp']
            )
        ) {
            throw new ApiException(
                'INVALID_ACCESS_TOKEN',
                'Access token ni veljaven.',
                401
            );
        }

        return $payload;
    }
}
