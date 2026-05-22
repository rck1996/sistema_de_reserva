<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../middlewares/auth.php';

try {
    json_response(array(
        'ok' => true,
        'claims' => authenticated_claims(),
        'user' => auth_service()->me(bearer_token()),
    ));
} catch (Throwable $exception) {
    json_response(array('ok' => false, 'error' => $exception->getMessage()), 401);
}
