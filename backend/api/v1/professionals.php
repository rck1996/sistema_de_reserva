<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../support/http.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../repositories/ProfessionalRepository.php';

$claims = authenticated_claims();
$companyId = tenant_company_id($claims);
$repository = new ProfessionalRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        json_response(array('ok' => true, 'data' => $repository->listByCompany($companyId)));
    }

    if ($method === 'POST') {
        authorize_roles($claims, array('super_admin', 'admin_empresa'));
        $payload = json_input();
        $professional = $repository->create($companyId, array(
            'name' => input_string($payload, 'name'),
            'email' => strtolower(input_string($payload, 'email')),
            'phone' => input_string($payload, 'phone', false),
            'bio' => input_string($payload, 'bio', false),
            'calendar_color' => input_string($payload, 'calendar_color', false) ?: '#22d3ee',
            'booking_capacity' => max(1, (int) ($payload['booking_capacity'] ?? 1)),
            'accepts_waitlist' => ($payload['accepts_waitlist'] ?? true) !== false,
            'is_active' => ($payload['is_active'] ?? true) !== false,
            'service_ids' => is_array($payload['service_ids'] ?? null) ? $payload['service_ids'] : array(),
        ));
        json_response(array('ok' => true, 'data' => $professional), 201);
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
