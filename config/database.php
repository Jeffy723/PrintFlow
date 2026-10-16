
<?php
function getDatabaseConnection(): PDO
{
    $hostName = strtolower(
        explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]
    );

    $isLocal = in_array(
        $hostName,
        ['localhost', '127.0.0.1'],
        true
    );

    $configFile = __DIR__ . (
        $isLocal
            ? '/database.local.php'
            : '/database.production.php'
    );

    if (!file_exists($configFile)) {
        throw new RuntimeException('Database configuration file is missing.');
    }

    $config = require $configFile;

    $host = getenv('DB_HOST') ?: ($config['host'] ?? '127.0.0.1');
    $port = getenv('DB_PORT') ?: ($config['port'] ?? '3306');
    $database = getenv('DB_NAME') ?: ($config['name'] ?? '');
    $username = getenv('DB_USER') ?: ($config['user'] ?? '');
    $password = getenv('DB_PASSWORD') ?: ($config['password'] ?? '');

    if ($database === '' || $username === '') {
        throw new RuntimeException('Database configuration is missing.');
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
