<?php
require_once __DIR__ . "/../../costume_log.php";

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
            throw new ApiException(
                'CERTIFICATE_VERIFICATION_FAILED',
                'Avtentikacija s certifikatom ni uspela.',
                401
            );
        }

        $clientCertificate = $_SERVER['SSL_CLIENT_CERT'] ?? '';

        if ($clientCertificate === '') {
            throw new ApiException(
                'CERTIFICATE_MISSING',
                'Klientov certifikat ni bil posredovan.',
                401
            );
        }

        $fingerprint = openssl_x509_fingerprint(
            $clientCertificate,
            'sha256'
        );

        if ($fingerprint === false) {
            throw new ApiException(
                'CERTIFICATE_INVALID',
                'Klientov certifikat ni veljaven.',
                401
            );
        }

        return $fingerprint;
    }

    // returns a user name, id and fingerprit of a connecterd user 
    public function verify()
    {
        $fingerprint = $this->getClientCertificateFingerprint();
        
        customLog($fingerprint);

        $stmt = $this->pdo->prepare("
            SELECT id, username
            FROM users
            WHERE certificate_fingerprint = ?
            LIMIT 1
        ");

        $stmt->execute([$fingerprint]);

        $user = $stmt->fetch();

        if (!$user) {
            throw new ApiException(
                'AUTHENTICATION_FAILED',
                'Prijava ni uspela.',
                401
            );
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'fingerprint' => $fingerprint
        ];
    }
}