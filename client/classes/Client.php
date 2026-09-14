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
        array $options = []
    ): array {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new Exception('cURL inicializacija ni uspela.');
        }

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CAINFO => $this->CaCrtPath,

            CURLOPT_SSLCERT => $this->ClientCrtPath,
            CURLOPT_SSLKEY => $this->ClientKeyPath,

            CURLOPT_TIMEOUT => 10,
        ];

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
                $data['sporocilo']
                ?? $data['message']
                ?? 'API napaka. HTTP status: ' . $httpCode
            );
        }

        return $data;
    }

    function connectToRegister(
        string $url,
        string $username
    ): array {
        $this->Username = $username;

        return $this->executeCurl(
            $url,
            [
                CURLOPT_POST => true,

                CURLOPT_POSTFIELDS => http_build_query([
                    'username' => $username
                ]),

                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
            ]
        );
    }

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
            empty($data['access_token']) ||
            empty($data['refresh_token'])
        ) {
            throw new Exception(
                $data['sporocilo']
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
            ]
        );

        if (
            empty($data['access_token']) ||
            empty($data['refresh_token'])
        ) {
            throw new Exception(
                $data['sporocilo']
                ?? 'Login odgovor nima tokenov.'
            );
        }

        return $data;
    }
}