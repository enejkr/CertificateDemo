<?php
/**
 * Zapiše poljubne podatke v custom log datoteko.
 *
 * Funkcija samodejno zazna tip podatkov in jih ustrezno formatira:
 * - array          → pretty JSON
 * - object         → pretty JSON
 * - JSON string    → decode + pretty JSON
 * - navaden string → izpiše kot string
 * - boolean        → true / false
 * - integer/float  → številčna vrednost
 * - null           → null
 * - binary data    → hex zapis
 * - resource       → tip resource-a
 *
 * @param mixed  $data   Podatek, ki ga želimo zapisati v log.
 *                       Sprejme katerikoli PHP tip: array, object,
 *                       string, JSON string, boolean, number, null itd.
 *
 * @param string $label  Opcijska oznaka log zapisa, ki se izpiše
 *                       pred podatki. Privzeto je prazna.
 *
 * @return void
 *
 * @example
 * customLog($data);
 *
 * @example
 * customLog($data, 'mTLS');
 *
 * @example
 * customLog(['client' => 'clientC'], 'REQUEST');
 */

function customLog($data, string $label = '')
{
    $logDir  = __DIR__ . '/logs';
    $logFile = $logDir . '/custom.log';

    // Ustvari logs mapo
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');

    // Formatiraj podatke
    $output = formatLogValue($data);

    // Label je opcijski
    $prefix = $label !== '' ? " [$label]" : '';

    file_put_contents(
        $logFile,
        "[$timestamp]$prefix $output" . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}


function formatLogValue($value): string
{
    // NULL
    if ($value === null) {
        return 'null';
    }

    // BOOLEAN
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    // INTEGER / FLOAT
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    // ARRAY
    if (is_array($value)) {
        return formatArray($value);
    }

    // OBJECT
    if (is_object($value)) {
        return formatObject($value);
    }

    // STRING
    if (is_string($value)) {
        return formatString($value);
    }

    // RESOURCE
    if (is_resource($value)) {
        return '[RESOURCE: ' . get_resource_type($value) . ']';
    }

    return '[UNKNOWN TYPE: ' . gettype($value) . ']';
}


function formatString(string $value): string
{
    // Prazna vrednost
    if ($value === '') {
        return '""';
    }

    // Preveri UTF-8
    $isUtf8 = mb_check_encoding($value, 'UTF-8');

    // Če ni veljaven UTF-8, obravnavaj kot binary
    if (!$isUtf8) {
        return '[BINARY] hex=' . bin2hex($value);
    }

    // Poskusi JSON decode
    $decoded = json_decode($value, true);

    if (
        json_last_error() === JSON_ERROR_NONE &&
        (is_array($decoded) || is_object($decoded))
    ) {
        return formatLogValue($decoded);
    }

    // Navaden string
    return $value;
}


function formatArray(array $array): string
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

    // Fallback, če JSON encoding ne uspe
    return print_r($array, true);
}


function formatObject(object $object): string
{
    // Najprej poskusi JSON
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

    // Fallback
    return print_r($object, true);
}
