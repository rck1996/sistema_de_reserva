<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $payload = json_input();
    json_response(auth_service()->refresh(input_string($payload, 'refresh_token')));
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 401);
}
