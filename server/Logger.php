<?php

class Logger
{
    private string $logDir;
    private bool $production;

    public function __construct(
        ?string $logDir = null,
        bool $production = false
    ) {
        $this->logDir = $logDir ?? (__DIR__ . '/logs');
        $this->production = $production;

        $this->createLogDirectory();
    }

    /*
      Zapiše podatke v log.
      Privzeti level: debug

      error              -> error.log
      info/debug/warning -> info.log

      V production načinu se debug logi ne shranjujejo.
    */

    public function log(
        mixed $data,
        string $level = 'debug'
    ): void {
        $level = strtolower(trim($level));

        // Če level manjka, uporabi debug.
        if (!$level) {
            $level = 'debug';
        }

        // V production načinu ne shranjuj debug logov.
        if ($this->production && $level === 'debug') {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        // message ločimo od ostalih podatkov.

        $message = null;

        if (is_array($data) && array_key_exists('message', $data)) {
            $message = $data['message'];
            unset($data['message']);
        }

        // formatiranje glavnega deka 
        $output = $this->formatValue($data);

        // Dodaj message pred JSON.
        if ($message !== null) {
            $message = $this->formatValue($message);

            $output = $message . ' ' . $output;
        }

        // make sure log is onlly one line 
        $output = $this->singleLine($output);

        $levelTag = strtoupper($level);

        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        $methodTag = $method !== null
            ? '[' . strtoupper($method) . '] '
            : '';

        $logLine = sprintf(
            '[%s] [%s] %s%s' . PHP_EOL,
            $timestamp,
            $levelTag,
            $methodTag,
            $output
        );

        // ločimo kam gre kaj
        $logFile = $level === 'error'
            ? $this->logDir . '/error.log'
            : $this->logDir . '/info.log';

        file_put_contents(
            $logFile,
            $logLine,
            FILE_APPEND | LOCK_EX
        );
    }


    // Ustvari logs mapo 
    private function createLogDirectory(): void
    {
        if (!is_dir($this->logDir)) {
            if (
                !mkdir($this->logDir, 0777, true)
                && !is_dir($this->logDir)
            ) {
                throw new RuntimeException(
                    'Log mape ni mogoče ustvariti: ' . $this->logDir
                );
            }
        }
    }


    // Formatira katerikoli podatkovni tip.
    private function formatValue(mixed $value): string
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


    //Formatira string
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
            json_last_error() === JSON_ERROR_NONE
            && (is_array($decoded) || is_object($decoded))
        ) {
            return $this->formatValue($decoded);
        }

        return $value;
    }

    // Formatira array v eno vrstico  
    private function formatArray(array $array): string
    {
        $json = json_encode(
            $array,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json !== false) {
            return $json;
        }

        return print_r($array, true);
    }

    // Formatira object kot enovrstični JSON.
    private function formatObject(object $object): string
    {
        $json = json_encode(
            $object,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json !== false) {
            return $json;
        }

        return print_r($object, true);
    }


    // last check for a one line log 
    private function singleLine(string $value): string
    {
        $value = str_replace(
            ["\r\n", "\r", "\n", "\t"],
            [' ', ' ', ' ', ' '],
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
