<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../middlewares/auth.php';
require_once __DIR__ . '/../../../repositories/ProfessionalRepository.php';

$claims = authenticated_claims();
authorize_roles($claims, array('staff'));
$companyId = tenant_company_id($claims);
$repository = new ProfessionalRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        json_response(array('ok' => true, 'data' => $repository->workday($companyId, (string) $claims['user_id'])));
    }

    if ($method === 'POST') {
        $payload = json_input();
        $block = $repository->createTimeBlock($companyId, (string) $claims['user_id'], array(
            'starts_at' => input_string($payload, 'starts_at'),
            'ends_at' => input_string($payload, 'ends_at'),
            'block_type' => input_string($payload, 'block_type', false) ?: 'block',
            'reason' => input_string($payload, 'reason', false),
        ));
        json_response(array('ok' => true, 'data' => $block), 201);
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
