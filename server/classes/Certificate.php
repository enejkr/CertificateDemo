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
            throw new Exception ("verification failed");
        }

        $clientCertificate = $_SERVER['SSL_CLIENT_CERT'] ?? '';

        if ($clientCertificate === '') {
            throw new Exception ("client certificate empty");
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
            throw new Exception ("certificate could not decode");
        }

        return strtoupper(hash('sha256', $certificateDer));
    }
    // returns a user name, id and fingerprit of a connecterd user 
    public function verify()
    {
        $fingerprint = $this->getClientCertificateFingerprint();

        $stmt = $this->pdo->prepare("
            SELECT id, username
            FROM users
            WHERE certificate_fingerprint = ?
            LIMIT 1
        ");

        $stmt->execute([$fingerprint]);

        $user = $stmt->fetch();

        if (!$user) {
           throw new Exception ("user not in Database");
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'fingerprint' => $fingerprint
        ];
    }
  
}