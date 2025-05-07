<?php

namespace SmartBin\Services;

use PDO;
use PDOException;
use SmartBin\Config\Config;

class DatabaseService
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $dbConfig = Config::getDatabaseConfig();
                $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
                self::$connection = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
                throw new \Exception('Erreur de connexion à la base de données');
            }
        }
        return self::$connection;
    }
}