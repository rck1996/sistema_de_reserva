<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../support/http.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../repositories/DisciplineRepository.php';

$claims = authenticated_claims();
$companyId = tenant_company_id($claims);
$repository = new DisciplineRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        json_response(array('ok' => true, 'data' => $repository->listByCompany($companyId)));
    }

    if ($method === 'POST') {
        authorize_roles($claims, array('super_admin', 'admin_empresa'));
        $payload = json_input();
        $discipline = $repository->create($companyId, array(
            'name' => input_string($payload, 'name'),
            'description' => input_string($payload, 'description', false),
            'color' => input_string($payload, 'color', false) ?: '#22d3ee',
            'is_active' => ($payload['is_active'] ?? true) !== false,
        ));
        json_response(array('ok' => true, 'data' => $discipline), 201);
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
