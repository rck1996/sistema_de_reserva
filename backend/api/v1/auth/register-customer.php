<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $payload = json_input();
    $companySlug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', input_string($payload, 'company_slug')));
    $companySlug = trim((string) $companySlug, '-');
    if ($companySlug === '') {
        json_response(array('ok' => false, 'error' => 'Slug de empresa invalido'), 422);
    }

    json_response(auth_service()->registerCustomer(
        $companySlug,
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
