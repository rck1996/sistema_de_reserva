<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/http.php';
require_once __DIR__ . '/../support/jwt.php';

function authenticated_claims(): array
{
    $config = require __DIR__ . '/../config/app.php';
    $token = bearer_token();
    if ($token === '') {
        json_response(array('ok' => false, 'error' => 'Token requerido'), 401);
    }

    try {
        return jwt_decode($token, (string) $config['jwt']['secret']);
    } catch (Throwable $exception) {
        json_response(array('ok' => false, 'error' => $exception->getMessage()), 401);
    }
}

function authorize_roles(array $claims, array $roles): void
{
    if (!in_array((string) ($claims['role'] ?? ''), $roles, true)) {
        json_response(array('ok' => false, 'error' => 'No autorizado'), 403);
    }
}

function tenant_company_id(array $claims): string
{
    $companyId = (string) ($claims['company_id'] ?? '');
    if ($companyId === '') {
        json_response(array('ok' => false, 'error' => 'Tenant requerido'), 403);
    }

    return $companyId;
}
