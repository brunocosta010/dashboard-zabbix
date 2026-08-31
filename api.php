<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
ini_set('html_errors', '0');

require_once __DIR__ . '/src/ZabbixClient.php';
$config = require __DIR__ . '/config.php';

try {
    $payload = (new ZabbixClient($config))->dashboard();
    if (ob_get_length() !== false) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    if (ob_get_length() !== false) ob_clean();
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'error' => true,
        'message' => $error->getMessage(),
        'updatedAt' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}