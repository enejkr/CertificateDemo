<?php
// loads all files in classes folder at the same time 
spl_autoload_register(function (string $class): void {

    $file = __DIR__ . '/classes/' . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});