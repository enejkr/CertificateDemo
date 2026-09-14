<?php

class Certificate
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    // returns a certificates fingerprint  
    private function getClientCertificateFingerprint()
    {
        $verify = $_SERVER['SSL_CLIENT_VERIFY'] ?? '';

        if ($verify !== 'SUCCESS') {
            return false;
        }

        $clientCertificate = $_SERVER['SSL_CLIENT_CERT'] ?? '';

        if ($clientCertificate === '') {
            return false;
        }

        $certificate = str_replace(
            [
                '-----BEGIN CERTIFICATE-----',
                '-----END CERTIFICATE-----',
                "\r",
                "\n",
                " ",
                "\t"
            ],
            '',
            $clientCertificate
        );

        $certificateDer = base64_decode($certificate, true);

        if ($certificateDer === false) {
            return false;
        }

        return strtoupper(hash('sha256', $certificateDer));
    }
    // returns a user name, id and fingerprit of a connecterd user 
    public function verify()
    {
        $fingerprint = $this->getClientCertificateFingerprint();

        if ($fingerprint === false) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, username
            FROM users
            WHERE certificate_fingerprint = ?
            LIMIT 1
        ");

        $stmt->execute([$fingerprint]);

        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'fingerprint' => $fingerprint
        ];
    }
    // ob registraciji vstavi uporabnika in poveže fingerprint z username 
    public function register($username)
    {
        $fingerprint = $this->getClientCertificateFingerprint();

        if ($fingerprint === false) {
            return [
                'error' => 'certificate',
                'message' => 'Certifikata ni mogoče pridobiti.'
            ];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, certificate_fingerprint)
            VALUES (?, ?)
        ");

        try {
            $stmt->execute([$username, $fingerprint]);
        } catch (PDOException $e) {

            if (
                $e->getCode() === '23000' &&
                isset($e->errorInfo[1]) &&
                (int)$e->errorInfo[1] === 1062
            ) {
                return [
                    'error' => 'duplicate',
                    'message' => 'Ta certifikat je že registriran.'
                ];
            }

            return [
                'error' => 'database',
                'message' => 'Napaka podatkovne baze.'
            ];
        }

        return [
            'id' => $this->pdo->lastInsertId(),
            'username' => $username,
            'fingerprint' => $fingerprint
        ];
    }
}