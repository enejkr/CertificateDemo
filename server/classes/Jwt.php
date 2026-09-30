<?php

class Jwt
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

        $this->algorithm = $config['algorithm'] ?? 'RS256';
        $this->expiresIn = (int)($config['expires_in'] ?? 3600);

        if ($this->algorithm !== 'RS256') {
            throw new InvalidArgumentException(
                'Podprt je samo RS256.'
            );
        }
    }

    
    // Ustvari access JWT .

    public function createAccessToken($user): string
    {
        $privateKey = file_get_contents($this->privateKeyPath);

        if ($privateKey === false) {
            throw new RuntimeException(
                'Private key ni mogoče prebrati.'
            );
        }

        $now = time();

        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
        ];

        $payload = [
            'sub' => (string)$user['id'],
            'username' => $user['username'],
            'iat' => $now,
            'exp' => $now + $this->expiresIn,
        ];

        $encodedHeader = $this->base64UrlEncode(
            json_encode(
                $header,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );

        $encodedPayload = $this->base64UrlEncode(
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );

        $data = $encodedHeader . '.' . $encodedPayload;

        $signature = '';

        $result = openssl_sign(
            $data,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        if ($result !== true) {
            throw new RuntimeException(
                'JWT podpisa ni mogoče ustvariti.'
            );
        }

        return $data . '.' . $this->base64UrlEncode($signature);
    }

    // preveri jwt
    public function verifyJwt(string $token): array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                throw new RuntimeException('Invalid JWT format.');
            }

            [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

            $headerJson = $this->base64UrlDecode($encodedHeader);

            $header = json_decode(
                $headerJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (
                !isset($header['alg']) ||
                $header['alg'] !== 'RS256'
            ) {
                throw new RuntimeException(
                    'Invalid JWT algorithm.'
                );
            }

            $payloadJson = $this->base64UrlDecode($encodedPayload);

            $payload = json_decode(
                $payloadJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($payload)) {
                throw new RuntimeException(
                    'Invalid JWT payload.'
                );
            }


            if (
                !isset(
                    $payload['sub'],
                    $payload['username'],
                    $payload['iat'],
                    $payload['exp']
                )
            ) {
                throw new RuntimeException(
                    'JWT payload nima vseh obveznih podatkov.'
                );
            }

            $now = time();

            if (!is_numeric($payload['iat'])) {
                throw new RuntimeException(
                    'Invalid iat.'
                );
            }

            if (!is_numeric($payload['exp'])) {
                throw new RuntimeException(
                    'Invalid exp.'
                );
            }

            if ((int)$payload['exp'] <= $now) {
                throw new RuntimeException(
                    'JWT je potekel.'
                );
            }

            
            // Preveri, da token ni iz prihodnosti. 
            if ((int)$payload['iat'] > $now ) {
                throw new RuntimeException(
                    'Invalid iat.'
                );
            }

            $publicKey = file_get_contents($this->publicKeyPath);

            if ($publicKey === false) {
                throw new RuntimeException(
                    'Public key ni mogoče prebrati.'
                );
            }

            $signature = $this->base64UrlDecode(
                $encodedSignature
            );

            $data = $encodedHeader . '.' . $encodedPayload;

            $valid = openssl_verify(
                $data,
                $signature,
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            if ($valid !== 1) {
                throw new RuntimeException(
                    'Invalid JWT signature.'
                );
            }

            return $payload;

        } catch (Throwable $e) {

            throw new ApiException(
                'INVALID_ACCESS_TOKEN',
                'Access token ni veljaven.',
                401
            );
        }
    }

    
    // Base64 encode.
    private function base64UrlEncode(string $data): string
    {
        return rtrim(
            strtr(
                base64_encode($data),
                '+/',
                '-_'
            ),
            '='
        );
    }

    // Base64 decode.
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat(
                '=',
                4 - $remainder
            );
        }

        $decoded = base64_decode(
            strtr(
                $data,
                '-_',
                '+/'
            ),
            true
        );

        if ($decoded === false) {
            throw new RuntimeException(
                'Invalid base64url data.'
            );
        }

        return $decoded;
    }
}