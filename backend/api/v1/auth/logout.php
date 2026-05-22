<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$payload = json_input();
auth_service()->logout(input_string($payload, 'refresh_token', false));
json_response(array('ok' => true));
