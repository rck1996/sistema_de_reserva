<?php

declare(strict_types=1);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../support/http.php';
require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../repositories/DashboardRepository.php';

$claims = authenticated_claims();
$companyId = tenant_company_id($claims);
$repository = new DashboardRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        authorize_roles($claims, array('super_admin', 'admin_empresa', 'staff'));
        json_response(array('ok' => true, 'data' => $repository->summary($companyId)));
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
