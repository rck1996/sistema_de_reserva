<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');

$pdo = app_pdo();
$accion = $_GET['accion'] ?? 'no';
require_csrf();

try {
    switch ($accion) {
        case 'profesional':
            $id = request_query_int('id_professional');
            $stmt = $pdo->prepare('DELETE FROM professionals WHERE id_professional = :id');
            $stmt->execute(array(':id' => $id));
            audit_log($pdo, 'professional', $id, 'deleted', 'Profesional eliminado');
            app_redirect('../admin-dashboard.php#profesionales', 'Profesional eliminado');
            break;

        case 'cliente':
            $id = request_query_int('id_cliente');
            $stmt = $pdo->prepare('DELETE FROM clientes WHERE id_cliente = :id');
            $stmt->execute(array(':id' => $id));
            audit_log($pdo, 'customer', $id, 'deleted', 'Cliente eliminado');
            app_redirect('../admin-dashboard.php#clientes', 'Cliente eliminado');
            break;

        case 'disciplina':
            $id = request_query_int('id_disciplina');
            $stmt = $pdo->prepare('DELETE FROM disciplinas WHERE id_disciplina = :id');
            $stmt->execute(array(':id' => $id));
            audit_log($pdo, 'discipline', $id, 'deleted', 'Disciplina eliminada');
            app_redirect('../admin-dashboard.php#disciplinas', 'Disciplina eliminada');
            break;

        case 'servicio':
            $id = request_query_int('id_servicio');
            $stmt = $pdo->prepare('DELETE FROM servicios WHERE id_servicio = :id');
            $stmt->execute(array(':id' => $id));
            audit_log($pdo, 'service', $id, 'deleted', 'Servicio eliminado');
            app_redirect('../admin-dashboard.php#servicios', 'Servicio eliminado');
            break;

        case 'evento':
            $id = request_query_int('id_evento');
            $event = fetch_one($pdo->prepare('SELECT * FROM eventos WHERE id_evento = :id'), array(':id' => $id));
            $stmt = $pdo->prepare('DELETE FROM eventos WHERE id_evento = :id');
            $stmt->execute(array(':id' => $id));
            audit_log($pdo, 'booking', $id, 'deleted', 'Reserva eliminada');
            if ($event !== null) {
                notify_waitlist_matches($pdo, $event);
            }
            app_redirect('../admin-dashboard.php#reservas', 'Reserva eliminada');
            break;

        default:
            app_redirect('../index.php');
    }
} catch (Throwable $exception) {
    handle_app_exception($exception, '../admin-dashboard.php');
}
