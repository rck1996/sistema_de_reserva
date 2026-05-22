<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../middlewares/auth.php';
require_once __DIR__ . '/../../../repositories/MarketplaceRepository.php';

$claims = authenticated_claims();
authorize_roles($claims, array('customer'));
$userId = (string) ($claims['user_id'] ?? '');
if ($userId === '') {
    json_response(array('ok' => false, 'error' => 'Usuario requerido'), 403);
}

try {
    $payload = json_input();
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', input_string($payload, 'company_slug')));
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        json_response(array('ok' => false, 'error' => 'Slug de empresa invalido'), 422);
    }

    json_response(array(
        'ok' => true,
        'data' => (new MarketplaceRepository(backend_pdo()))->enrollCustomer($userId, $slug),
    ));
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
