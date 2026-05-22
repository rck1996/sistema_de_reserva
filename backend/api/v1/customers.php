<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../support/http.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../repositories/CustomerRepository.php';

$claims = authenticated_claims();
$companyId = tenant_company_id($claims);
$repository = new CustomerRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        json_response(array('ok' => true, 'data' => $repository->listByCompany($companyId)));
    }

    if ($method === 'POST') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        $payload = json_input();
        $customer = $repository->create($companyId, array(
            'first_name' => input_string($payload, 'first_name'),
            'last_name' => input_string($payload, 'last_name'),
            'email' => strtolower(input_string($payload, 'email')),
            'phone' => input_string($payload, 'phone', false),
            'notes' => input_string($payload, 'notes', false),
            'is_active' => ($payload['is_active'] ?? true) !== false,
        ));
        json_response(array('ok' => true, 'data' => $customer), 201);
    }

    if ($method === 'PATCH') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        $payload = json_input();
        $customer = $repository->update($companyId, input_string($payload, 'id'), array(
            'first_name' => input_string($payload, 'first_name'),
            'last_name' => input_string($payload, 'last_name'),
            'email' => strtolower(input_string($payload, 'email')),
            'phone' => input_string($payload, 'phone', false),
            'notes' => input_string($payload, 'notes', false),
            'is_active' => ($payload['is_active'] ?? true) !== false,
        ));
        json_response(array('ok' => true, 'data' => $customer));
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
