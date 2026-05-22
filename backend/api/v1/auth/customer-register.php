<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $payload = json_input();
    json_response(auth_service()->registerGlobalCustomer(
        strtolower(input_string($payload, 'email')),
        input_string($payload, 'password'),
        input_string($payload, 'first_name'),
        input_string($payload, 'last_name'),
        input_string($payload, 'phone', false),
        input_string($payload, 'notes', false)
    ), 201);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
