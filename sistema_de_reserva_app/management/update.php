<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');

$pdo = app_pdo();
$accion = $_GET['accion'] ?? 'no';

try {
    switch ($accion) {
        case 'profesional':
            $id = request_query_int('id_professional');
            $usuario = request_post_string('user_professional');
            $nombre = request_post_string('name_professional');
            $email = validate_email_address(request_post_string('email_professional'));
            $telefono = validate_phone(request_post_string('phone_professional'));
            $bio = request_post_string('bio_professional', false);
            $color = validate_color(request_post_string('calendar_color'));
            $newPassword = request_post_string('pass_professional', false);
            $disciplina = request_post_string('id_disciplina', false);
            $activo = request_post_string('activo', false) === '0' ? 0 : 1;

            ensure_unique_value($pdo, 'professionals', 'user_professional', $usuario, $id, 'id_professional');
            ensure_unique_value($pdo, 'professionals', 'email_professional', $email, $id, 'id_professional');

            $current = fetch_one($pdo->prepare('SELECT pass_professional FROM professionals WHERE id_professional = :id'), array(':id' => $id));
            if ($current === null) {
                throw new InvalidArgumentException('Profesional no encontrado');
            }

            $passwordToStore = $newPassword === '' ? $current['pass_professional'] : hash_password_value($newPassword);

            $stmt = $pdo->prepare(
                'UPDATE professionals
                 SET user_professional = :usuario, pass_professional = :password, name_professional = :nombre,
                     email_professional = :email, phone_professional = :telefono, bio_professional = :bio,
                     calendar_color = :color, id_disciplina = :id_disciplina, activo = :activo
                 WHERE id_professional = :id'
            );
            $stmt->execute(
                array(
                    ':usuario' => $usuario,
                    ':password' => $passwordToStore,
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':telefono' => $telefono,
                    ':bio' => $bio,
                    ':color' => $color,
                    ':id_disciplina' => $disciplina === '' ? null : (int) $disciplina,
                    ':activo' => $activo,
                    ':id' => $id,
                )
            );

            app_redirect('../admin-dashboard.php#profesionales', 'Profesional actualizado');
            break;

        case 'cliente':
            $id = request_query_int('id_cliente');
            $nombre = request_post_string('nombre_cliente');
            $apellido = request_post_string('apellido_cliente');
            $telefono = validate_phone(request_post_string('telefono_cliente'));
            $correo = validate_email_address(request_post_string('correo_cliente'));
            $usuario = request_post_string('user_cliente');
            $newPassword = request_post_string('pass_cliente', false);
            $notas = request_post_string('notas_cliente', false);

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
                     correo_cliente = :correo, user_cliente = :usuario, pass_cliente = :password, notas_cliente = :notas
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
                    ':notas' => $notas,
                    ':id' => $id,
                )
            );

            app_redirect('../admin-dashboard.php#clientes', 'Cliente actualizado');
            break;

        case 'disciplina':
            $id = request_query_int('id_disciplina');
            $stmt = $pdo->prepare(
                'UPDATE disciplinas
                 SET nombre_disciplina = :nombre, descripcion_disciplina = :descripcion, color_disciplina = :color, activa = :activa
                 WHERE id_disciplina = :id'
            );
            $stmt->execute(
                array(
                    ':nombre' => request_post_string('nombre_disciplina'),
                    ':descripcion' => request_post_string('descripcion_disciplina', false),
                    ':color' => validate_color(request_post_string('color_disciplina')),
                    ':activa' => request_post_string('activa', false) === '0' ? 0 : 1,
                    ':id' => $id,
                )
            );
            app_redirect('../admin-dashboard.php#disciplinas', 'Disciplina actualizada');
            break;

        case 'servicio':
            $id = request_query_int('id_servicio');
            $current = fetch_one($pdo->prepare('SELECT img_servicio FROM servicios WHERE id_servicio = :id'), array(':id' => $id));
            if ($current === null) {
                throw new InvalidArgumentException('Servicio no encontrado');
            }

            $imagen = save_service_image($_FILES['img_servicio'] ?? array(), (string) $current['img_servicio']);

            $stmt = $pdo->prepare(
                'UPDATE servicios
                 SET id_disciplina = :id_disciplina, nombre_servicio = :nombre, descripcion_servicio = :descripcion,
                     precio_servicio = :precio, duracion_minutos = :duracion, modalidad_servicio = :modalidad,
                     img_servicio = :imagen, color = :color, textColor = :textColor, activo = :activo
                 WHERE id_servicio = :id'
            );
            $stmt->execute(
                array(
                    ':id_disciplina' => request_post_string('id_disciplina', false) === '' ? null : (int) request_post_string('id_disciplina', false),
                    ':nombre' => request_post_string('nombre_servicio'),
                    ':descripcion' => request_post_string('descripcion_servicio'),
                    ':precio' => (float) request_post_string('precio_servicio'),
                    ':duracion' => max(15, (int) request_post_string('duracion_minutos')),
                    ':modalidad' => request_post_string('modalidad_servicio'),
                    ':imagen' => $imagen,
                    ':color' => validate_color(request_post_string('color')),
                    ':textColor' => validate_color(request_post_string('textColor')),
                    ':activo' => request_post_string('activo', false) === '0' ? 0 : 1,
                    ':id' => $id,
                )
            );

            app_redirect('../admin-dashboard.php#servicios', 'Servicio actualizado');
            break;

        case 'evento':
            $id = request_query_int('id_evento');
            $idProfessional = (int) request_post_string('professional_id');
            $idCliente = (int) request_post_string('txt_cliente');
            $idServicio = (int) request_post_string('txt_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end, $id);

            $stmt = $pdo->prepare(
                'UPDATE eventos
                 SET id_professional = :id_professional, id_cliente = :id_cliente, id_servicio = :id_servicio,
                     start = :start, end = :end, notas_reserva = :notas, estado_reserva = :estado
                 WHERE id_evento = :id_evento'
            );
            $stmt->execute(
                array(
                    ':id_professional' => $idProfessional,
                    ':id_cliente' => $idCliente,
                    ':id_servicio' => $idServicio,
                    ':start' => $start,
                    ':end' => $end,
                    ':notas' => request_post_string('notas_reserva', false),
                    ':estado' => request_post_string('estado_reserva', false) ?: 'confirmada',
                    ':id_evento' => $id,
                )
            );

            app_redirect('../admin-dashboard.php#reservas', 'Reserva actualizada');
            break;

        default:
            app_redirect('../index.php');
    }
} catch (Throwable $exception) {
    handle_app_exception($exception, '../admin-dashboard.php');
}
