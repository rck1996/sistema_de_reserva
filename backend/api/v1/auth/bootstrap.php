<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../database/connection.php';
require_once __DIR__ . '/../../../support/http.php';
require_once __DIR__ . '/../../../services/AuthService.php';

function auth_service(): AuthService
{
    $pdo = backend_pdo();

    return new AuthService(
        new UserRepository($pdo),
        new RefreshTokenRepository($pdo)
    );
}
