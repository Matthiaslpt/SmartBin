<?php

namespace SmartBin\services;

use PDO;
use PDOException;
use SmartBin\config\config;

class DatabaseService
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $config = Config::getDatabaseConfig();
                $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
                self::$connection = new PDO($dsn, $config['user'], $config['password']);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
                exit;
            }
        }
        return self::$connection;
    }
}