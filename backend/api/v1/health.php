<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$payload = array(
    'ok' => true,
    'api' => 'v1',
    'service' => 'sistema_de_reserva',
    'database' => array(
        'driver' => 'pgsql',
        'connected' => false,
    ),
);

try {
    backend_pdo()->query('SELECT 1');
    $payload['database']['connected'] = true;
} catch (Throwable $exception) {
    http_response_code(503);
    $payload['ok'] = false;
    $payload['error'] = 'PostgreSQL unavailable';
}

echo json_encode($payload);
