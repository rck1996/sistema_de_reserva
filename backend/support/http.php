<?php

declare(strict_types=1);

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_response(array('ok' => false, 'error' => 'JSON invalido'), 400);
    }

    return $payload;
}

function input_string(array $payload, string $key, bool $required = true): string
{
    $value = trim((string) ($payload[$key] ?? ''));
    if ($required && $value === '') {
        json_response(array('ok' => false, 'error' => 'Campo requerido: ' . $key), 422);
    }

    return $value;
}

function bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
        return '';
    }

    return trim(substr($header, 7));
}
