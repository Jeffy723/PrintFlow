<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'staff']);

header('Content-Type: application/json; charset=utf-8');

function respondWithQuotationItems(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWithQuotationItems(405, ['error' => 'Method not allowed.']);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    respondWithQuotationItems(403, ['error' => 'Your request could not be verified. Please refresh the page and try again.']);
}

$customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$quotationId = filter_var($_POST['quotation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($customerId === false || $quotationId === false) {
    respondWithQuotationItems(400, ['error' => 'Select a valid customer and quotation.']);
}

try {
    $pdo = getDatabaseConnection();
    $quotationStmt = $pdo->prepare(
        'SELECT qi.id, qi.service_id, qi.description, qi.quantity, qi.unit_price, qi.total_price,
                qi.specifications, s.name AS service_name, s.is_active
        FROM quotations q
        LEFT JOIN quotation_items qi ON qi.quotation_id = q.id
        LEFT JOIN services s ON s.id = qi.service_id
        WHERE q.id = :quotation_id AND q.customer_id = :customer_id AND q.status = :status
        ORDER BY qi.id ASC'
    );
    $quotationStmt->execute([
        ':quotation_id' => $quotationId,
        ':customer_id' => $customerId,
        ':status' => 'Accepted',
    ]);
    $rows = $quotationStmt->fetchAll();
} catch (Throwable $exception) {
    error_log('Order quotation item lookup failed: ' . $exception->getMessage());
    respondWithQuotationItems(500, ['error' => 'Quotation items could not be loaded. Please try again.']);
}

if ($rows === []) {
    respondWithQuotationItems(404, ['error' => 'The quotation does not belong to the selected customer or could not be found.']);
}

$items = [];
foreach ($rows as $row) {
    if ($row['id'] === null) {
        continue;
    }

    $items[] = [
        'quotation_item_id' => (int) $row['id'],
        'service_id' => (int) $row['service_id'],
        'description' => (string) $row['description'],
        'service_name' => (string) $row['service_name'],
        'quantity' => (int) $row['quantity'],
        'unit_price' => (float) $row['unit_price'],
        'total_price' => (float) $row['total_price'],
        'specifications' => (string) ($row['specifications'] ?? ''),
        'is_active' => (int) $row['is_active'] === 1,
    ];
}

respondWithQuotationItems(200, ['items' => $items]);
