<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_cliente', '3', '../index.php');

$pdo = app_pdo();
require_csrf();

try {
    $stmt = $pdo->prepare('DELETE FROM eventos WHERE id_evento = :id_evento AND id_cliente = :id_cliente');
    $stmt->execute(
        array(
            ':id_evento' => request_query_int('id_evento'),
            ':id_cliente' => request_session_int('id_cliente'),
        )
    );

    app_redirect('../customer-dashboard.php', 'Reserva eliminada');
} catch (Throwable $exception) {
    handle_app_exception($exception, '../customer-dashboard.php');
}
