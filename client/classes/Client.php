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

            CURLOPT_HEADER => true,

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

        $requestHeaders = '';

        if (isset($options[CURLOPT_HTTPHEADER])) {
            $requestHeaders = implode(
                "\r\n",
                $options[CURLOPT_HTTPHEADER]
            );
        }

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

        $headerSize = curl_getinfo(
            $ch,
            CURLINFO_HEADER_SIZE
        );

        $responseHeader = substr(
            $response,
            0,
            $headerSize
        );

        $responseBody = substr(
            $response,
            $headerSize
        );

        $contentType = curl_getinfo(
            $ch,
            CURLINFO_CONTENT_TYPE
        );

        $totalTime = curl_getinfo(
            $ch,
            CURLINFO_TOTAL_TIME
        );

        $primaryIp = curl_getinfo(
            $ch,
            CURLINFO_PRIMARY_IP
        );

        $sslVerifyResult = curl_getinfo(
            $ch,
            CURLINFO_SSL_VERIFYRESULT
        );

        curl_close($ch);

        $data = json_decode(
            $responseBody,
            true
        );

        $debug = [
            'url' => $url,
            'http_code' => $httpCode,
            'request_headers' => $requestHeaders,
            'response_headers' => $responseHeader,
            'response_body' => $responseBody,
            'content_type' => $contentType,
            'total_time' => $totalTime,
            'primary_ip' => $primaryIp,
            'ssl_verify_result' => $sslVerifyResult
        ];

        if (!is_array($data)) {
            throw new Exception(
                $this->formatDebugResponse($debug)
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception(
                $this->formatDebugResponse($debug)
            );
        }

        if (
            !isset($data['success']) ||
            $data['success'] !== true
        ) {
            throw new Exception(
                $this->formatDebugResponse($debug)
            );
        }

        return [
            'data' => $data['data'] ?? [],
            'http_code' => $httpCode,
            'headers' => $responseHeader,
            'body' => $responseBody,
            'request_headers' => $requestHeaders,
            'debug' => $debug,
            'success' => $data['success'] ?? false,
            'message' => $data['message'] ?? null,
            'error' => $data['error'] ?? null
        ];
    }

    private function formatDebugResponse(array $debug): string
    {
        return
            "URL:\n" .
            $debug['url'] . "\n\n" .

            "HTTP STATUS:\n" .
            $debug['http_code'] . "\n\n" .

            "REQUEST HEADERS:\n" .
            ($debug['request_headers'] ?: '(ni podatka)') . "\n\n" .

            "RESPONSE HEADERS:\n" .
            $debug['response_headers'] . "\n\n" .

            "RESPONSE BODY:\n" .
            $debug['response_body'] . "\n\n" .

            "CONTENT TYPE:\n" .
            ($debug['content_type'] ?? '(ni podatka)') . "\n\n" .

            "TOTAL TIME:\n" .
            $debug['total_time'] . " s\n\n" .

            "SERVER IP:\n" .
            ($debug['primary_ip'] ?? '(ni podatka)') . "\n\n" .

            "SSL VERIFY RESULT:\n" .
            $debug['ssl_verify_result'];
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
                "Login odgovor nima tokenov.\n\n" .
                "HTTP STATUS:\n" .
                $data['http_code'] . "\n\n" .
                "REQUEST HEADERS:\n" .
                $data['request_headers'] . "\n\n" .
                "RESPONSE HEADERS:\n" .
                $data['headers'] . "\n\n" .
                "RESPONSE BODY:\n" .
                $data['body']
            );
        }

        return $data;
    }
}