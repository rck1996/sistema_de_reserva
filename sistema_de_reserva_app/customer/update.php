<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_cliente', '3', '../index.php');

$pdo = app_pdo();
$accion = $_GET['accion'] ?? 'no';

try {
    switch ($accion) {
        case 'cliente':
            $id = request_session_int('id_cliente');
            $nombre = request_post_string('nombre_cliente');
            $apellido = request_post_string('apellido_cliente');
            $telefono = validate_phone(request_post_string('telefono_cliente'));
            $correo = validate_email_address(request_post_string('correo_cliente'));
            $usuario = request_post_string('user_cliente');
            $newPassword = request_post_string('pass_cliente', false);

            ensure_unique_value($pdo, 'clientes', 'correo_cliente', $correo, $id, 'id_cliente');
            ensure_unique_value($pdo, 'clientes', 'user_cliente', $usuario, $id, 'id_cliente');

            $current = fetch_one($pdo->prepare('SELECT pass_cliente FROM clientes WHERE id_cliente = :id'), array(':id' => $id));
            if ($current === null) {
                throw new InvalidArgumentException('Cliente no encontrado');
            }

            $passwordToStore = $newPassword === '' ? $current['pass_cliente'] : hash_password_value($newPassword);

            $stmt = $pdo->prepare(
                'UPDATE clientes
                 SET nombre_cliente = :nombre, apellido_cliente = :apellido, telefono_cliente = :telefono,
                     correo_cliente = :correo, user_cliente = :usuario, pass_cliente = :password
                 WHERE id_cliente = :id'
            );
            $stmt->execute(
                array(
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':telefono' => $telefono,
                    ':correo' => $correo,
                    ':usuario' => $usuario,
                    ':password' => $passwordToStore,
                    ':id' => $id,
                )
            );

            $_SESSION['nombre_cliente'] = $nombre;
            $_SESSION['apellido_cliente'] = $apellido;

            app_redirect('../customer-dashboard.php', 'Tus datos fueron actualizados');
            break;

        case 'evento':
            $idEvento = request_query_int('id_evento');
            $idProfessional = (int) request_post_string('professional_id');
            $idServicio = (int) request_post_string('txt_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end, $idEvento);

            $stmt = $pdo->prepare(
                'UPDATE eventos
                 SET id_professional = :id_professional, id_servicio = :id_servicio, start = :start, end = :end, notas_reserva = :notas
                 WHERE id_evento = :id_evento AND id_cliente = :id_cliente'
            );
            $stmt->execute(
                array(
                    ':id_professional' => $idProfessional,
                    ':id_servicio' => $idServicio,
                    ':start' => $start,
                    ':end' => $end,
                    ':notas' => request_post_string('notas_reserva', false),
                    ':id_evento' => $idEvento,
                    ':id_cliente' => request_session_int('id_cliente'),
                )
            );

            app_redirect('../customer-dashboard.php', 'Reserva actualizada');
            break;

        default:
            app_redirect('../index.php');
    }
} catch (Throwable $exception) {
    handle_app_exception($exception, '../customer-dashboard.php');
}
