<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../middlewares/auth.php';
require_once __DIR__ . '/../../../repositories/SuperAdminRepository.php';

$claims = authenticated_claims();
authorize_roles($claims, array('super_admin'));
$repository = new SuperAdminRepository(backend_pdo());
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        json_response(array('ok' => true, 'data' => $repository->overview()));
    }

    if ($method === 'PATCH') {
        $payload = json_input();
        $company = $repository->updateCompanyProfile(input_string($payload, 'id'), array(
            'name' => input_string($payload, 'name'),
            'display_name' => input_string($payload, 'display_name'),
            'tagline' => input_string($payload, 'tagline', false),
            'description' => input_string($payload, 'description', false),
            'city' => input_string($payload, 'city', false),
            'contact_email' => strtolower(input_string($payload, 'contact_email', false)),
            'contact_phone' => input_string($payload, 'contact_phone', false),
            'primary_color' => input_string($payload, 'primary_color', false) ?: '#22d3ee',
            'accent_color' => input_string($payload, 'accent_color', false) ?: '#8b5cf6',
            'is_public' => ($payload['is_public'] ?? true) !== false,
        ));
        json_response(array('ok' => true, 'data' => $company));
    }

    json_response(array('ok' => false, 'error' => 'Metodo no soportado'), 405);
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 400);
}
