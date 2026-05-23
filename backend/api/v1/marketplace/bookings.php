<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../middlewares/auth.php';
require_once __DIR__ . '/../../../repositories/BookingRepository.php';

$claims = authenticated_claims();
authorize_roles($claims, array('customer'));

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
    }

    $payload = json_input();
    $repository = new BookingRepository(backend_pdo());
    $booking = $repository->createFromMarketplaceCustomer((string) $claims['user_id'], array(
        'company_slug' => input_string($payload, 'company_slug'),
        'professional_id' => input_string($payload, 'professional_id'),
        'service_id' => input_string($payload, 'service_id'),
        'starts_at' => input_string($payload, 'starts_at'),
        'notes' => input_string($payload, 'notes', false),
    ));

    json_response(array('ok' => true, 'data' => $booking), 201);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
