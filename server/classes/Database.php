<?php

class Database
{
    private PDO $pdo;

    public function __construct()
    {
        $host = 'localhost';
        $db = 'mtls_demo';
        $user = 'root';
        $pass = '';

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