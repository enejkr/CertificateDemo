<?php
// mabey some time extend this class for more info for internal logging
class ApiException extends Exception
{
    private int $statusCode;
    private string $errorCode;

    public function __construct(
        string $errorCode,
        string $message,
        int $statusCode
    ) {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
