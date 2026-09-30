<?php

class Client
{
    private string $CaCrtPath;
    private string $ClientCrtPath;
    private string $ClientKeyPath;
    private ?string $Username;

    public function __construct(
        string $CaCrtPath,
        string $ClientCrtPath,
        string $ClientKeyPath,
        ?string $Username = null
    ) {
        $this->CaCrtPath = $CaCrtPath;
        $this->ClientCrtPath = $ClientCrtPath;
        $this->ClientKeyPath = $ClientKeyPath;
        $this->Username = $Username;
    }

    private function executeCurl(
        string $url,
        array $options = [],
        bool $useClientCertificate = false
    ): array {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new Exception('cURL inicializacija ni uspela.');
        }

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CAINFO => $this->CaCrtPath,

            CURLOPT_TIMEOUT => 10,
        ];

        if ($useClientCertificate) {
            $defaultOptions[CURLOPT_SSLCERT] = $this->ClientCrtPath;
            $defaultOptions[CURLOPT_SSLKEY] = $this->ClientKeyPath;
        }

        curl_setopt_array(
            $ch,
            $defaultOptions + $options
        );

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception(
                'cURL napaka: ' . $error
            );
        }

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        $data = json_decode(
            $response,
            true
        );

        if (!is_array($data)) {
            throw new Exception(
                'API ni vrnil veljavnega JSON-a. ' .
                'HTTP: ' . $httpCode .
                ' | Odgovor: ' . $response
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception(
                $data['error']['message']
                ?? 'API napaka. HTTP status: ' . $httpCode
            );
        }

        if (
            !isset($data['success']) ||
            $data['success'] !== true
        ) {
            throw new Exception(
                $data['error']['message']
                ?? 'API je vrnil neuspešen odgovor.'
            );
        }

        return $data;
    }

    // connects to an api 
    // dose not youse a certificate 
    function connectViaAccessToken(
        string $accessToken,
        string $url
    ): array {
        return $this->executeCurl(
            $url,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Accept: application/json'
                ],
            ]
        );
    }

    // returns new access and new refreash tokens
    //dise not send a certificate
    function connectViaRefreshToken(
        string $refreshToken,
        string $url
    ): array {
        $data = $this->executeCurl(
            $url,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $refreshToken,
                    'Accept: application/json'
                ],
            ]
        );

        if (
            empty($data['data']['access_token']) ||
            empty($data['data']['refresh_token'])
        ) {
            throw new Exception(
                $data['error']['message']
                ?? 'Refresh odgovor nima tokenov.'
            );
        }

        return $data;
    }

    /*
    function connects to a server via certificate and returens access an refreash token
    */
    function connectViaCertificate(
        string $url
    ): array {
        $data = $this->executeCurl(
            $url,
            [
                CURLOPT_POST => true,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ],
            ],
            true
        );

        if (
            empty($data['data']['access_token']) ||
            empty($data['data']['refresh_token'])
        ) {
            throw new Exception(
                $data['error']['message']
                ?? 'Login odgovor nima tokenov.'
            );
        }

        return $data;
    }
}
