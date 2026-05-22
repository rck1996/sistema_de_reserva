<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../support/http.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../repositories/ServiceRepository.php';

$claims = authenticated_claims();
$companyId = tenant_company_id($claims);
$repository = new ServiceRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        json_response(array('ok' => true, 'data' => $repository->listByCompany($companyId)));
    }

    if ($method === 'POST') {
        authorize_roles($claims, array('super_admin', 'admin_empresa'));
        $payload = json_input();
        $service = $repository->create($companyId, array(
            'discipline_id' => input_string($payload, 'discipline_id', false),
            'name' => input_string($payload, 'name'),
            'description' => input_string($payload, 'description', false),
            'price' => max(0, (float) ($payload['price'] ?? 0)),
            'duration_minutes' => max(15, (int) ($payload['duration_minutes'] ?? 60)),
            'modality' => input_string($payload, 'modality', false) ?: 'Presencial',
            'color' => input_string($payload, 'color', false) ?: '#22d3ee',
            'is_active' => ($payload['is_active'] ?? true) !== false,
        ));
        json_response(array('ok' => true, 'data' => $service), 201);
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
