<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/env.php';

backend_load_env(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');

return array(
    'driver' => 'pgsql',
    'host' => backend_env('DB_HOST', '127.0.0.1'),
    'port' => (int) backend_env('DB_PORT', '5432'),
    'database' => backend_env('DB_NAME', 'sistema_de_reserva'),
    'username' => backend_env('DB_USER', 'sistema_de_reserva'),
    'password' => backend_env('DB_PASSWORD', ''),
);
