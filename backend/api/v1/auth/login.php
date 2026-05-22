<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $payload = json_input();
    json_response(auth_service()->login(
        input_string($payload, 'company_slug', false) ?: 'demo',
        strtolower(input_string($payload, 'email')),
        input_string($payload, 'password')
    ));
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 401);
}
