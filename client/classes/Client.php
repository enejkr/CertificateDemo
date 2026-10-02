<?php

class Client
{
    private string $CaCrtPath;
    private string $ClientCrtPath;
    private string $ClientKeyPath;
    private ?string $Client_name;

    public function __construct(
        string $CaCrtPath,
        string $ClientCrtPath,
        string $ClientKeyPath,
        ?string $Client_name = null
    ) {
        $this->CaCrtPath = $CaCrtPath;
        $this->ClientCrtPath = $ClientCrtPath;
        $this->ClientKeyPath = $ClientKeyPath;
        $this->Client_name = $Client_name;
    }

    /**
     * Izvede HTTP request.
     */
    private function executeCurl(
        string $url,
        array $options = [],
        bool $useClientCertificate = false
    ): array {

        $ch = curl_init($url);

        if ($ch === false) {
            throw new Exception(
                'cURL inicializacija ni uspela.'
            );
        }

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,

            // Vrni header + body
            CURLOPT_HEADER => true,

            CURLOPT_CAINFO => $this->CaCrtPath,

            CURLOPT_TIMEOUT => 10,

            CURLOPT_CONNECTTIMEOUT => 5,

            CURLOPT_FOLLOWLOCATION => false,
        ];

        /*
         * Client certificate samo za certificate login.
         */
        if ($useClientCertificate) {

            $defaultOptions[CURLOPT_SSLCERT] =
                $this->ClientCrtPath;

            $defaultOptions[CURLOPT_SSLKEY] =
                $this->ClientKeyPath;
        }

        curl_setopt_array(
            $ch,
            $defaultOptions + $options
        );

        /*
         * REQUEST HEADERS
         */
        $requestHeaders = '';

        if (isset($options[CURLOPT_HTTPHEADER])) {

            $requestHeaders = implode(
                "\r\n",
                $options[CURLOPT_HTTPHEADER]
            );
        }

        /*
         * REQUEST
         */
        $response = curl_exec($ch);

        if ($response === false) {

            $error = curl_error($ch);
            $errorCode = curl_errno($ch);

            curl_close($ch);

            throw new Exception(
                'cURL napaka (' .
                    $errorCode .
                    '): ' .
                    $error
            );
        }

        /*
         * CURL INFO
         */
        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $headerSize = curl_getinfo(
            $ch,
            CURLINFO_HEADER_SIZE
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

        /*
         * RESPONSE HEADER
         */
        $responseHeader = substr(
            $response,
            0,
            $headerSize
        );

        /*
         * RESPONSE BODY
         */
        $responseBody = substr(
            $response,
            $headerSize
        );

        /*
         * PARSE HEADER
         */
        $parsedHeaders = $this->parseHeaders(
            $responseHeader
        );

        /*
         * TOKEN EXPIRATION HEADERJI
         */
        $accessTokenExp = $this->getHeader(
            $parsedHeaders,
            'Access-Token-Exp'
        );

        $refreshTokenExp = $this->getHeader(
            $parsedHeaders,
            'Refresh-Token-Exp'
        );

        /*
         * JSON
         */
        $data = json_decode(
            $responseBody,
            true
        );

        /*
         * DEBUG
         */
        $debug = [
            'endpoint' => $url,

            'http_code' => $httpCode,

            'request_headers' => $requestHeaders,

            'response_headers' => $responseHeader,

            'parsed_headers' => $parsedHeaders,

            'access_token_exp' => $accessTokenExp,

            'refresh_token_exp' => $refreshTokenExp,

            'response_body' => $responseBody,

            'content_type' => $contentType,

            'total_time' => $totalTime,

            'primary_ip' => $primaryIp,

            'ssl_verify_result' => $sslVerifyResult,

            'json_error' => json_last_error_msg(),
        ];

        /*
         * JSON mora biti veljaven.
         */
        if (!is_array($data)) {

            throw new Exception(
                $this->formatDebugResponse($debug)
            );
        }

        /*
         * HTTP status.
         */
        if (
            $httpCode < 200 ||
            $httpCode >= 300
        ) {

            throw new Exception(
                $this->formatDebugResponse($debug)
            );
        }

        /*
         * API success.
         */
        if (
            !isset($data['success']) ||
            $data['success'] !== true
        ) {

            throw new Exception(
                $this->formatDebugResponse($debug)
            );
        }

        /*
         * RESPONSE
         */
        return [
            'data' => $data['data'] ?? [],

            'http_code' => $httpCode,

            /*
             * Originalni response header.
             */
            'headers' => $responseHeader,

            /*
             * Headerji kot array.
             */
            'parsed_headers' => $parsedHeaders,

            /*
             * Token expiry headerji.
             */
            'access_token_exp' => $accessTokenExp,

            'refresh_token_exp' => $refreshTokenExp,

            'body' => $responseBody,

            'request_headers' => $requestHeaders,

            'debug' => $debug,

            'success' => $data['success'] ?? false,

            'message' => $data['message'] ?? null,

            'error' => $data['error'] ?? null,
        ];
    }


    /**
     * HTTP headerje spremeni v array.
     */
    private function parseHeaders(
        string $headerString
    ): array {

        $headers = [];

        $lines = preg_split(
            "/\r\n|\n|\r/",
            trim($headerString)
        );

        foreach ($lines as $line) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /*
             * Preskoči:
             *
             * HTTP/1.1 200 OK
             */
            if (!str_contains($line, ':')) {
                continue;
            }

            [
                $name,
                $value
            ] = explode(
                ':',
                $line,
                2
            );

            $name = strtolower(
                trim($name)
            );

            $value = trim($value);

            /*
             * HTTP headerji so case-insensitive.
             */
            if (isset($headers[$name])) {

                if (!is_array($headers[$name])) {

                    $headers[$name] = [
                        $headers[$name]
                    ];
                }

                $headers[$name][] = $value;
            } else {

                $headers[$name] = $value;
            }
        }

        return $headers;
    }


    /**
     * Pridobi header brez upoštevanja velikosti črk.
     */
    private function getHeader(
        array $headers,
        string $name
    ): ?string {

        $name = strtolower($name);

        if (!isset($headers[$name])) {
            return null;
        }

        $value = $headers[$name];

        if (is_array($value)) {
            $value = end($value);
        }

        return (string)$value;
    }


    /**
     * GET/POST API z Access Tokenom.
     */
    public function connectViaAccessToken(
        string $accessToken,
        string $url
    ): array {

        return $this->executeCurl(
            $url,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' .
                        $accessToken,

                    'Accept: application/json',
                ],
            ]
        );
    }


    /**
     * Refresh endpoint.
     */
    public function connectViaRefreshToken(
        string $refreshToken,
        string $url
    ): array {

        $result = $this->executeCurl(
            $url,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' .
                        $refreshToken,

                    'Accept: application/json',
                ],
            ]
        );

        /*
         * Preveri nova tokena.
         */
        if (
            empty($result['data']['access_token']) ||
            empty($result['data']['refresh_token'])
        ) {

            throw new Exception(
                $result['error']['message']
                    ??
                    'Refresh odgovor nima tokenov.'
            );
        }

        return $result;
    }


    /**
     * Login preko client certificate.
     */
    public function connectViaCertificate(
        string $url
    ): array {

        $result = $this->executeCurl(
            $url,
            [
                CURLOPT_POST => true,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],
            ],
            true
        );

        /*
         * Login mora vrniti oba tokena.
         */
        if (
            empty($result['data']['access_token']) ||
            empty($result['data']['refresh_token'])
        ) {

            throw new Exception(
                "Login odgovor nima tokenov.\n\n" .
                    $this->formatDebugResponse(
                        $result['debug']
                    )
            );
        }

        return $result;
    }


    /**
     * Debug izpis.
     */
    public function formatDebugResponse(
        array $debug
    ): string {

        return
            "========================================\n" .
            "API REQUEST\n" .
            "========================================\n\n" .

            "ENDPOINT:\n" .
            $debug['endpoint'] .
            "\n\n" .

            "HTTP STATUS:\n" .
            $debug['http_code'] .
            "\n\n" .

            "REQUEST HEADERS:\n" .
            (
                $debug['request_headers']
                ?: '(ni podatka)'
            ) .
            "\n\n" .

            "RESPONSE HEADERS:\n" .
            (
                $debug['response_headers']
                ?: '(ni podatka)'
            ) .
            "\n\n" .

            "PARSED RESPONSE HEADERS:\n" .
            json_encode(
                $debug['parsed_headers'],
                JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE
            ) .
            "\n\n" .

            "ACCESS-TOKEN-EXP:\n" .
            (
                $debug['access_token_exp']
                ?? '(header manjka)'
            ) .
            "\n\n" .

            "REFRESH-TOKEN-EXP:\n" .
            (
                $debug['refresh_token_exp']
                ?? '(header manjka)'
            ) .
            "\n\n" .

            "RESPONSE BODY:\n" .
            $debug['response_body'] .
            "\n\n" .

            "CONTENT TYPE:\n" .
            (
                $debug['content_type']
                ?? '(ni podatka)'
            ) .
            "\n\n" .

            "TOTAL TIME:\n" .
            $debug['total_time'] .
            " s\n\n" .

            "SERVER IP:\n" .
            (
                $debug['primary_ip']
                ?? '(ni podatka)'
            ) .
            "\n\n" .

            "SSL VERIFY RESULT:\n" .
            $debug['ssl_verify_result'] .
            "\n\n" .

            "JSON ERROR:\n" .
            $debug['json_error'] .
            "\n" .

            "========================================\n";
    }
}
