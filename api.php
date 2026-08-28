<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
require_once __DIR__ . '/src/ZabbixClient.php';
$config = require __DIR__ . '/config.php';
try {
    echo json_encode((new ZabbixClient($config))->dashboard(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(502);
    echo json_encode(['error' => true, 'message' => $error->getMessage(), 'updatedAt' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE);
}