<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../middlewares/auth.php';
require_once __DIR__ . '/../../../repositories/CustomerPortalRepository.php';

$claims = authenticated_claims();
authorize_roles($claims, array('customer'));
$userId = (string) ($claims['user_id'] ?? '');
if ($userId === '') {
    json_response(array('ok' => false, 'error' => 'Usuario requerido'), 403);
}

try {
    json_response(array(
        'ok' => true,
        'data' => (new CustomerPortalRepository(backend_pdo()))->bookingsForUser($userId),
    ));
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
