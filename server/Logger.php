<?php

class Logger
{
    private string $logDir;
    private string $logFile;

    public function __construct(
        ?string $logDir = null,
        string $logFileName = 'custom.log'
    ) {
        $this->logDir = $logDir ?? (__DIR__ . '/logs');
        $this->logFile = $this->logDir . '/' . $logFileName;

        $this->createLogDirectory();
    }

    /**
     * Zapiše poljubne podatke v log datoteko.
     *
     * @param mixed  $data
     * @param string $label
     */
    public function log($data, string $label = ''): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $output = $this->formatValue($data);

        $prefix = $label !== ''
            ? " [$label]"
            : '';

        file_put_contents(
            $this->logFile,
            "[$timestamp]$prefix $output" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Ustvari logs mapo, če še ne obstaja.
     */
    private function createLogDirectory(): void
    {
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0777, true) && !is_dir($this->logDir)) {
                throw new RuntimeException(
                    'Log mape ni mogoče ustvariti: ' . $this->logDir
                );
            }
        }
    }

    /**
     * Formatira katerikoli PHP tip.
     */
    private function formatValue($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        if (is_object($value)) {
            return $this->formatObject($value);
        }

        if (is_string($value)) {
            return $this->formatString($value);
        }

        if (is_resource($value)) {
            return '[RESOURCE: ' . get_resource_type($value) . ']';
        }

        return '[UNKNOWN TYPE: ' . gettype($value) . ']';
    }

    /**
     * Formatira string.
     *
     * Če je string JSON, ga pretvori v lepši JSON.
     * Če ni veljaven UTF-8, ga obravnava kot binary data.
     */
    private function formatString(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            return '[BINARY] hex=' . bin2hex($value);
        }

        $decoded = json_decode($value, true);

        if (
            json_last_error() === JSON_ERROR_NONE &&
            (is_array($decoded) || is_object($decoded))
        ) {
            return $this->formatValue($decoded);
        }

        return $value;
    }

    /**
     * Formatira array kot pretty JSON.
     */
    private function formatArray(array $array): string
    {
        $json = json_encode(
            $array,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json !== false) {
            return $json;
        }

        return print_r($array, true);
    }

    /**
     * Formatira object kot pretty JSON.
     */
    private function formatObject(object $object): string
    {
        $json = json_encode(
            $object,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json !== false) {
            return $json;
        }

        return print_r($object, true);
    }
}
