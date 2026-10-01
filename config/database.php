<?php

declare(strict_types=1);

function getDatabaseConnection(): PDO
{
    $localConfigFile = __DIR__ . '/database.local.php';

    $localConfig = [];

    if (file_exists($localConfigFile)) {
        $localConfig = require $localConfigFile;
    }

    $host = getenv('DB_HOST') ?: ($localConfig['host'] ?? '127.0.0.1');
    $port = getenv('DB_PORT') ?: ($localConfig['port'] ?? '3306');
    $database = getenv('DB_NAME') ?: ($localConfig['name'] ?? '');
    $username = getenv('DB_USER') ?: ($localConfig['user'] ?? '');
    $password = getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? '');

    if ($database === '' || $username === '') {
        throw new RuntimeException(
            'Database configuration is missing.'
        );
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}