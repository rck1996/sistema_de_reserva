<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode(array(
    'ok' => true,
    'service' => 'sistema_de_reserva',
    'message' => 'API backend activo. Usa /api/v1/health.php para validar estado.',
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
