<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = app_pdo();
$accion = $_GET['accion'] ?? 'leer';

try {
    switch ($accion) {
        case 'agendar_customer':
            require_role('id_cliente', '3', '../index.php');

            $idProfessional = (int) request_post_string('professional_id');
            $idCliente = request_session_int('id_cliente');
            $idServicio = (int) request_post_string('id_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end);

            $stmt = $pdo->prepare('INSERT INTO eventos (title, id_professional, id_cliente, id_servicio, start, end, notas_reserva) VALUES (:title, :id_professional, :id_cliente, :id_servicio, :start, :end, :notas)');
            $stmt->execute(
                array(
                    ':title' => 'Reservado',
                    ':id_professional' => $idProfessional,
                    ':id_cliente' => $idCliente,
                    ':id_servicio' => $idServicio,
                    ':start' => $start,
                    ':end' => $end,
                    ':notas' => request_post_string('notas_reserva', false),
                )
            );

            echo json_encode(array('ok' => true));
            break;

        case 'agendar_staff':
            require_role('id_professional', '2', '../staff-login.php');

            $idProfessional = request_session_int('id_professional');
            $idCliente = (int) request_post_string('id_cliente');
            $idServicio = (int) request_post_string('id_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end);

            $stmt = $pdo->prepare('INSERT INTO eventos (title, id_professional, id_cliente, id_servicio, start, end, notas_reserva) VALUES (:title, :id_professional, :id_cliente, :id_servicio, :start, :end, :notas)');
            $stmt->execute(
                array(
                    ':title' => 'Reservado',
                    ':id_professional' => $idProfessional,
                    ':id_cliente' => $idCliente,
                    ':id_servicio' => $idServicio,
                    ':start' => $start,
                    ':end' => $end,
                    ':notas' => request_post_string('notas_reserva', false),
                )
            );

            echo json_encode(array('ok' => true));
            break;

        case 'eliminar':
            $idEvento = (int) request_post_string('id_evento');
            $stmt = $pdo->prepare('DELETE FROM eventos WHERE id_evento = :id_evento');
            $stmt->execute(array(':id_evento' => $idEvento));
            echo json_encode(array('ok' => true));
            break;

        default:
            $stmt = $pdo->prepare(
                'SELECT eventos.id_evento, eventos.title, clientes.user_cliente, clientes.nombre_cliente, clientes.apellido_cliente,
                        clientes.telefono_cliente, professionals.name_professional, professionals.calendar_color,
                        servicios.nombre_servicio, servicios.precio_servicio, servicios.color, servicios.textColor,
                        disciplinas.nombre_disciplina, eventos.start, eventos.end, eventos.estado_reserva, eventos.notas_reserva
                 FROM eventos
                 JOIN professionals ON eventos.id_professional = professionals.id_professional
                 JOIN clientes ON eventos.id_cliente = clientes.id_cliente
                 JOIN servicios ON eventos.id_servicio = servicios.id_servicio
                 LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
                 ORDER BY eventos.start ASC'
            );
            echo json_encode(fetch_all($stmt));
            break;
    }
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => $exception->getMessage()));
}
