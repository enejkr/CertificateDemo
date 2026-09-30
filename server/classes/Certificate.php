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
            throw new ApiException(
                'CERTIFICATE_VERIFICATION_FAILED',
                'Certificate authentication failed.',
                401
            );
        }

        $clientCertificate = $_SERVER['SSL_CLIENT_CERT'] ?? '';

        if ($clientCertificate === '') {
            throw new ApiException(
                'CERTIFICATE_MISSING',
                'Client certificate was not provided.',
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
                'Client certificate is invalid.',
                401
            );
        }

        return $fingerprint;
    }

    // returns a client name, id and fingerprit of a connecterd client 
    public function verify()
    {
        $fingerprint = $this->getClientCertificateFingerprint();
        
        $stmt = $this->pdo->prepare("
            SELECT id, client_name
            FROM Clients
            WHERE certificate_fingerprint = ?
            LIMIT 1
        ");

        $stmt->execute([$fingerprint]);

        $client = $stmt->fetch();

        if (!$client) {
            throw new ApiException(
                'AUTHENTICATION_FAILED',
                'Authentication failed.',
                401
            );
        }

        return [
            'id' => $client['id'],
            'client_name' => $client['client_name'],
            'fingerprint' => $fingerprint
        ];
    }
}