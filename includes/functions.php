<?php

declare(strict_types=1);

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appBasePath(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $basePath = rtrim(dirname($scriptName), '/\\');

    if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
        return '';
    }

    return $basePath;
}

function assetUrl(string $path): string
{
    $basePath = appBasePath();
    $assetPath = '/' . ltrim($path, '/');

    return $basePath === '' ? $assetPath : $basePath . $assetPath;
}

function money(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

function paginateQuery(PDO $pdo, string $table, string $select = '*', string $where = '1=1', array $params = [], string $orderBy = 'id DESC', int $page = 1, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM {$table} WHERE {$where}");
    $countStmt->execute($params);
    $count = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT {$select} FROM {$table} WHERE {$where} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    return [
        'total' => $count,
        'page' => $page,
        'perPage' => $perPage,
        'data' => $stmt->fetchAll(),
    ];
}
