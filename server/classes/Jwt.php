<?php
// HEADER.PAYLOAD.SIGNATURE 
class Jwt
{
    private string $privateKeyPath;
    private string $publicKeyPath;

    public function __construct(string $privateKeyPath, string $publicKeyPath)
    {
        $this->privateKeyPath = $privateKeyPath;
        $this->publicKeyPath = $publicKeyPath;
    }
    // JWT uses a special base64url encode 
    // function switches + for - and / for _ 
    // and removes = 
    private function base64UrlEncode($data)
    {
        return rtrim(
            strtr(base64_encode($data), '+/', '-_'),
            '='
        );
    }

    // for a user creates accesToken 
    public function createAccessToken($user, $expiresIn = 20)
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $payload = [
            'sub' => (string)$user['id'],
            'username' => $user['username'],
            'iat' => time(),
            'exp' => time() + $expiresIn
        ];

        $headerEncoded = $this->base64UrlEncode(
            json_encode($header)
        );

        $payloadEncoded = $this->base64UrlEncode(
            json_encode($payload)
        );

        $data = $headerEncoded . '.' . $payloadEncoded;

        $privateKey = file_get_contents(
            $this->privateKeyPath
        );

        if ($privateKey === false) {
            throw new Exception('Private key ni mogoče prebrati.');
        }

        $signature = '';

        openssl_sign(
            $data,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        $signatureEncoded = $this->base64UrlEncode($signature);

        return $data . '.' . $signatureEncoded;
    }

    public function verifyJwt(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new Exception('JWT ni pravilne oblike.');
        }

        // stil crazy cant get over this 
        [$header, $payload, $signature] = $parts;

        // Header
        $headerJson = base64_decode(
            strtr($header, '-_', '+/')
        );

        $headerData = json_decode($headerJson, true);

        if (!$headerData) {
            throw new Exception('Neveljaven JWT header.');
        }

        // Preveri algoritem
        if (($headerData['alg'] ?? '') !== 'RS256') {
            throw new Exception('JWT ne uporablja RS256.');
        }

        // Payload
        $payloadJson = base64_decode(
            strtr($payload, '-_', '+/')
        );

        $payloadData = json_decode($payloadJson, true);

        if (!$payloadData) {
            throw new Exception('Neveljaven JWT payload.');
        }

        // Signature
        $signatureBinary = base64_decode(
            strtr($signature, '-_', '+/')
        );

        // Naloži public key
        $publicKey = file_get_contents(
            $this->publicKeyPath
        );

        if ($publicKey === false) {
            throw new Exception('Public key ni mogoče prebrati.');
        }

        // Preveri podpis
        $result = openssl_verify(
            $header . '.' . $payload,
            $signatureBinary,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($result !== 1) {
            throw new Exception('JWT podpis ni veljaven.');
        }

        // Preveri expiration
        if (isset($payloadData['exp']) && time() >= $payloadData['exp']) {
            throw new Exception('JWT je potekel.');
        }

        return $payloadData;
    }
}