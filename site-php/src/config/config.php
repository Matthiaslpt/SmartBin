<?php

namespace SmartBin\Config;

class Config
{
    // Configuration de la base de données
    public static function getDatabaseConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'postgres',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'dbname' => $_ENV['DB_NAME'] ?? 'smartbin',
            'user' => $_ENV['DB_USER'] ?? 'smartbin_user',
            'password' => $_ENV['DB_PASSWORD'] ?? 'secure_password',
        ];
    }
}