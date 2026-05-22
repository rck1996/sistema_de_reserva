<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/env.php';

backend_load_env(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');

return array(
    'env' => backend_env('APP_ENV', 'local'),
    'url' => backend_env('APP_URL', 'http://127.0.0.1:8000'),
    'frontend_url' => backend_env('FRONTEND_URL', 'http://127.0.0.1:5173'),
    'jwt' => array(
        'secret' => backend_env('JWT_SECRET', ''),
        'refresh_secret' => backend_env('JWT_REFRESH_SECRET', ''),
        'access_ttl' => (int) backend_env('JWT_ACCESS_TTL', '900'),
        'refresh_ttl' => (int) backend_env('JWT_REFRESH_TTL', '2592000'),
    ),
    'mercado_pago' => array(
        'access_token' => backend_env('MP_ACCESS_TOKEN', ''),
        'public_key' => backend_env('MP_PUBLIC_KEY', ''),
    ),
);
