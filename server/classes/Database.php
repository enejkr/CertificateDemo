<?php

class Database
{
    private PDO $pdo;

    public function __construct()
    {
        $config = parse_ini_file(
            __DIR__ . '/../config/server.ini',
            true
        );

        if ($config === false) {
            throw new RuntimeException('Unable to load server.ini');
        }

        $host = $config['database']['db_host'];
        $db   = $config['database']['db_name'];
        $user = $config['database']['db_user'];
        $pass = $config['database']['db_password'];

        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            http_response_code(500);

            header('Content-Type: application/json');

            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);

            exit;
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}