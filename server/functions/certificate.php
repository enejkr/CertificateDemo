<?php

function getClientCertificateFingerprint()
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

function verifyClientCertificate($pdo)
{
    $fingerprint = getClientCertificateFingerprint();

    if ($fingerprint === false) {
        return false;
    }

    $stmt = $pdo->prepare("
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

function register($pdo, $username)
{
    $fingerprint = getClientCertificateFingerprint();

    if ($fingerprint === false) {
        return [
            'error' => 'certificate',
            'message' => 'Certifikata ni mogoče pridobiti.'
        ];
    }

    $stmt = $pdo->prepare("
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
        'id' => $pdo->lastInsertId(),
        'username' => $username,
        'fingerprint' => $fingerprint
    ];
}