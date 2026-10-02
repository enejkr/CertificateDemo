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
                'Only RS256 is supported.'
            );
        }
    }


    // Ustvari access JWT .

    public function createAccessToken($client): string
    {
        $privateKey = file_get_contents($this->privateKeyPath);

        if ($privateKey === false) {
            throw new RuntimeException(
                'Unable to read private key.'
            );
        }

        $now = time();

        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
        ];

        $payload = [
            'client_id' => (string)$client['id'],
            'client_name' => $client['client_name'],
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
                'Unable to create JWT signature.'
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
                    $payload['client_id'],
                    $payload['client_name'],
                    $payload['iat'],
                    $payload['exp']
                )
            ) {
                throw new RuntimeException(
                    'JWT payload is missing required data.'
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
                    'JWT has expired.'
                );
            }


            // Preveri, da token ni iz prihodnosti. 
            if ((int)$payload['iat'] > $now) {
                throw new RuntimeException(
                    'Invalid iat.'
                );
            }

            $publicKey = file_get_contents($this->publicKeyPath);

            if ($publicKey === false) {
                throw new RuntimeException(
                    'Unable to read public key.'
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
                'Access token is invalid.',
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
