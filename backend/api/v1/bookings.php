<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../support/http.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../repositories/BookingRepository.php';

$claims = authenticated_claims();
$companyId = tenant_company_id($claims);
$repository = new BookingRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        json_response(array('ok' => true, 'data' => $repository->listByCompany($companyId)));
    }

    if ($method === 'POST') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        $payload = json_input();
        $booking = $repository->create($companyId, array(
            'customer_id' => input_string($payload, 'customer_id'),
            'professional_id' => input_string($payload, 'professional_id'),
            'service_id' => input_string($payload, 'service_id'),
            'starts_at' => input_string($payload, 'starts_at'),
            'status' => input_string($payload, 'status', false) ?: 'pending',
            'notes' => input_string($payload, 'notes', false),
        ));
        json_response(array('ok' => true, 'data' => $booking), 201);
    }

    if ($method === 'PATCH') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        $payload = json_input();
        $bookingId = input_string($payload, 'id');

        if (isset($payload['starts_at'])) {
            $booking = $repository->reschedule($companyId, $bookingId, input_string($payload, 'starts_at'));
            json_response(array('ok' => true, 'data' => $booking));
        }

        $booking = $repository->updateStatus($companyId, $bookingId, input_string($payload, 'status'));
        json_response(array('ok' => true, 'data' => $booking));
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
