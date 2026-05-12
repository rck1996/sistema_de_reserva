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
            require_csrf();

            $idProfessional = request_post_int('professional_id');
            $idCliente = request_session_int('id_cliente');
            $idServicio = request_post_int('id_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end);

            $pdo->prepare('INSERT INTO eventos (title, id_professional, id_cliente, id_servicio, start, end, notas_reserva) VALUES (:title, :id_professional, :id_cliente, :id_servicio, :start, :end, :notas)')
                ->execute(
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
            require_csrf();

            $idProfessional = request_session_int('id_professional');
            $idCliente = request_post_int('id_cliente');
            $idServicio = request_post_int('id_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end);

            $pdo->prepare('INSERT INTO eventos (title, id_professional, id_cliente, id_servicio, start, end, notas_reserva) VALUES (:title, :id_professional, :id_cliente, :id_servicio, :start, :end, :notas)')
                ->execute(
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

        case 'update_event':
            require_csrf();
            if (empty($_SESSION['id_professional']) && empty($_SESSION['id_admin'])) {
                throw new RuntimeException('No autorizado');
            }
            $idEvento = request_post_int('id_evento');
            $start = request_post_string('start');
            $end = request_post_string('end');
            $estado = request_post_string('estado_reserva', false);
            $notas = request_post_string('notas_reserva', false);

            $event = fetch_one($pdo->prepare('SELECT * FROM eventos WHERE id_evento = :id_evento'), array(':id_evento' => $idEvento));
            if ($event === null) {
                throw new InvalidArgumentException('Reserva no encontrada');
            }

            if (!empty($_SESSION['id_professional']) && (int) $event['id_professional'] !== request_session_int('id_professional') && empty($_SESSION['id_admin'])) {
                throw new RuntimeException('No puedes modificar esa reserva');
            }

            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);
            ensure_slot_available($pdo, (int) $event['id_professional'], $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s'), $idEvento);

            $pdo->prepare(
                'UPDATE eventos
                 SET start = :start, end = :end, estado_reserva = :estado_reserva, notas_reserva = :notas_reserva
                 WHERE id_evento = :id_evento'
            )->execute(
                array(
                    ':start' => $startDate->format('Y-m-d H:i:s'),
                    ':end' => $endDate->format('Y-m-d H:i:s'),
                    ':estado_reserva' => $estado !== '' ? $estado : $event['estado_reserva'],
                    ':notas_reserva' => $notas !== '' ? $notas : $event['notas_reserva'],
                    ':id_evento' => $idEvento,
                )
            );

            echo json_encode(array('ok' => true));
            break;

        case 'update_event_admin':
            require_role('id_admin', '1', '../admin-login.php');
            require_csrf();
            $idEvento = request_post_int('id_evento');
            $idProfessional = request_post_int('id_professional', false);
            $estado = request_post_string('estado_reserva', false);
            $notas = request_post_string('notas_reserva', false);
            $start = request_post_string('start', false);
            $end = request_post_string('end', false);

            $event = fetch_one($pdo->prepare('SELECT * FROM eventos WHERE id_evento = :id_evento'), array(':id_evento' => $idEvento));
            if ($event === null) {
                throw new InvalidArgumentException('Reserva no encontrada');
            }

            $targetProfessional = $idProfessional > 0 ? $idProfessional : (int) $event['id_professional'];
            $targetStart = $start !== '' ? (new DateTimeImmutable($start))->format('Y-m-d H:i:s') : (string) $event['start'];
            $targetEnd = $end !== '' ? (new DateTimeImmutable($end))->format('Y-m-d H:i:s') : (string) $event['end'];
            ensure_slot_available($pdo, $targetProfessional, $targetStart, $targetEnd, $idEvento);

            $pdo->prepare(
                'UPDATE eventos
                 SET id_professional = :id_professional, start = :start, end = :end, estado_reserva = :estado_reserva, notas_reserva = :notas_reserva
                 WHERE id_evento = :id_evento'
            )->execute(
                array(
                    ':id_professional' => $targetProfessional,
                    ':start' => $targetStart,
                    ':end' => $targetEnd,
                    ':estado_reserva' => $estado !== '' ? $estado : $event['estado_reserva'],
                    ':notas_reserva' => $notas !== '' ? $notas : $event['notas_reserva'],
                    ':id_evento' => $idEvento,
                )
            );

            echo json_encode(array('ok' => true));
            break;

        case 'eliminar':
            require_csrf();
            if (empty($_SESSION['id_professional']) && empty($_SESSION['id_admin'])) {
                throw new RuntimeException('No autorizado');
            }
            $idEvento = request_post_int('id_evento');
            $event = fetch_one($pdo->prepare('SELECT * FROM eventos WHERE id_evento = :id_evento'), array(':id_evento' => $idEvento));
            if ($event === null) {
                throw new InvalidArgumentException('Reserva no encontrada');
            }
            if (!empty($_SESSION['id_professional']) && (int) $event['id_professional'] !== request_session_int('id_professional') && empty($_SESSION['id_admin'])) {
                throw new RuntimeException('No puedes eliminar esa reserva');
            }

            $pdo->prepare('DELETE FROM eventos WHERE id_evento = :id_evento')->execute(array(':id_evento' => $idEvento));
            echo json_encode(array('ok' => true));
            break;

        default:
            $stmt = $pdo->prepare(
                'SELECT eventos.id_evento, eventos.title, eventos.id_professional, eventos.id_cliente, eventos.id_servicio,
                        clientes.user_cliente, clientes.nombre_cliente, clientes.apellido_cliente, clientes.telefono_cliente,
                        professionals.name_professional, professionals.calendar_color,
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
