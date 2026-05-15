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
            $usuario = request_post_string('user_professional');
            $nombre = request_post_string('name_professional');
            $email = validate_email_address(request_post_string('email_professional'));
            $telefono = validate_phone(request_post_string('phone_professional'));
            $bio = request_post_string('bio_professional', false);
            $color = validate_color(request_post_string('calendar_color'));
            $newPassword = request_post_string('pass_professional', false);
            $disciplina = request_post_string('id_disciplina', false);
            $activo = request_post_string('activo', false) === '0' ? 0 : 1;
            $bookingCapacity = max(1, request_post_int('booking_capacity', false) ?: 1);
            $acceptsWaitlist = request_post_string('accepts_waitlist', false) === '0' ? 0 : 1;

            ensure_unique_value($pdo, 'professionals', 'user_professional', $usuario, $id, 'id_professional');
            ensure_unique_value($pdo, 'professionals', 'email_professional', $email, $id, 'id_professional');

            $current = fetch_one($pdo->prepare('SELECT pass_professional FROM professionals WHERE id_professional = :id'), array(':id' => $id));
            if ($current === null) {
                throw new InvalidArgumentException('Profesional no encontrado');
            }

            $passwordToStore = $newPassword === '' ? $current['pass_professional'] : hash_password_value($newPassword);

            $pdo->prepare(
                'UPDATE professionals
                 SET user_professional = :usuario, pass_professional = :password, name_professional = :nombre,
                     email_professional = :email, phone_professional = :telefono, bio_professional = :bio,
                     calendar_color = :color, id_disciplina = :id_disciplina, activo = :activo,
                     booking_capacity = :booking_capacity, accepts_waitlist = :accepts_waitlist,
                     notification_email = :notification_email, notification_whatsapp = :notification_whatsapp
                 WHERE id_professional = :id'
            )->execute(
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
                    ':booking_capacity' => $bookingCapacity,
                    ':accepts_waitlist' => $acceptsWaitlist,
                    ':notification_email' => $email,
                    ':notification_whatsapp' => $telefono,
                    ':id' => $id,
                )
            );

            $disciplineIds = array_values(array_unique(array_map('intval', (array) ($_POST['discipline_ids'] ?? array()))));
            if ($disciplina !== '' && !in_array((int) $disciplina, $disciplineIds, true)) {
                $disciplineIds[] = (int) $disciplina;
            }
            $pdo->prepare('DELETE FROM professional_disciplines WHERE id_professional = :id')->execute(array(':id' => $id));
            $disciplineStmt = $pdo->prepare('INSERT OR IGNORE INTO professional_disciplines (id_professional, id_disciplina) VALUES (:id_professional, :id_disciplina)');
            foreach ($disciplineIds as $disciplineId) {
                if ($disciplineId > 0) {
                    $disciplineStmt->execute(array(':id_professional' => $id, ':id_disciplina' => $disciplineId));
                }
            }

            $serviceIds = array_values(array_unique(array_map('intval', (array) ($_POST['service_ids'] ?? array()))));
            $pdo->prepare('DELETE FROM professional_services WHERE id_professional = :id')->execute(array(':id' => $id));
            $serviceStmt = $pdo->prepare('INSERT OR IGNORE INTO professional_services (id_professional, id_servicio) VALUES (:id_professional, :id_servicio)');
            foreach ($serviceIds as $serviceId) {
                if ($serviceId > 0) {
                    $serviceStmt->execute(array(':id_professional' => $id, ':id_servicio' => $serviceId));
                }
            }

            audit_log($pdo, 'professional', $id, 'updated', 'Profesional actualizado');
            app_redirect('../admin-dashboard.php#profesionales', 'Profesional actualizado');
            break;

        case 'professional_schedule':
            $id = request_query_int('id_professional');
            ensure_professional_schedule_rows($pdo, $id);

            foreach (range(0, 6) as $weekday) {
                $isWorking = request_post_string('is_working_' . $weekday, false) === '1' ? 1 : 0;
                $startTime = validate_time_value(request_post_string('start_time_' . $weekday, false), true);
                $endTime = validate_time_value(request_post_string('end_time_' . $weekday, false), true);
                $breakStart = validate_time_value(request_post_string('break_start_' . $weekday, false), true);
                $breakEnd = validate_time_value(request_post_string('break_end_' . $weekday, false), true);
                $slotInterval = max(5, request_post_int('slot_interval_' . $weekday, false) ?: (int) setting_value('slot_interval', '30'));

                if ($isWorking === 1 && ($startTime === '' || $endTime === '')) {
                    throw new InvalidArgumentException('Cada dia activo debe tener horario de inicio y termino');
                }

                $pdo->prepare(
                    'UPDATE professional_availability
                     SET is_working = :is_working, start_time = :start_time, end_time = :end_time,
                         break_start = :break_start, break_end = :break_end, slot_interval = :slot_interval
                     WHERE id_professional = :id_professional AND weekday = :weekday'
                )->execute(
                    array(
                        ':is_working' => $isWorking,
                        ':start_time' => $startTime !== '' ? $startTime : '09:00',
                        ':end_time' => $endTime !== '' ? $endTime : '18:00',
                        ':break_start' => $breakStart,
                        ':break_end' => $breakEnd,
                        ':slot_interval' => $slotInterval,
                        ':id_professional' => $id,
                        ':weekday' => $weekday,
                    )
                );
            }

            $exceptionDate = request_post_string('exception_date', false);
            if ($exceptionDate !== '') {
                $pdo->prepare(
                    'INSERT INTO professional_exceptions (id_professional, exception_date, is_day_off, start_time, end_time, notes)
                     VALUES (:id_professional, :exception_date, :is_day_off, :start_time, :end_time, :notes)
                     ON CONFLICT(id_professional, exception_date) DO UPDATE SET
                        is_day_off = excluded.is_day_off,
                        start_time = excluded.start_time,
                        end_time = excluded.end_time,
                        notes = excluded.notes'
                )->execute(
                    array(
                        ':id_professional' => $id,
                        ':exception_date' => $exceptionDate,
                        ':is_day_off' => request_post_string('exception_day_off', false) === '1' ? 1 : 0,
                        ':start_time' => validate_time_value(request_post_string('exception_start_time', false), true),
                        ':end_time' => validate_time_value(request_post_string('exception_end_time', false), true),
                        ':notes' => request_post_string('exception_notes', false),
                    )
                );
            }

            audit_log($pdo, 'professional_schedule', $id, 'updated', 'Disponibilidad profesional actualizada');
            app_redirect('../management/professional-edit.php?id_professional=' . $id, 'Disponibilidad actualizada');
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

            $pdo->prepare(
                'UPDATE clientes
                 SET nombre_cliente = :nombre, apellido_cliente = :apellido, telefono_cliente = :telefono,
                     correo_cliente = :correo, user_cliente = :usuario, pass_cliente = :password, notas_cliente = :notas
                 WHERE id_cliente = :id'
            )->execute(
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

            audit_log($pdo, 'customer', $id, 'updated', 'Cliente actualizado');
            app_redirect('../admin-dashboard.php#clientes', 'Cliente actualizado');
            break;

        case 'disciplina':
            $id = request_query_int('id_disciplina');
            $pdo->prepare(
                'UPDATE disciplinas
                 SET nombre_disciplina = :nombre, descripcion_disciplina = :descripcion, color_disciplina = :color, activa = :activa
                 WHERE id_disciplina = :id'
            )->execute(
                array(
                    ':nombre' => request_post_string('nombre_disciplina'),
                    ':descripcion' => request_post_string('descripcion_disciplina', false),
                    ':color' => validate_color(request_post_string('color_disciplina')),
                    ':activa' => request_post_string('activa', false) === '0' ? 0 : 1,
                    ':id' => $id,
                )
            );
            audit_log($pdo, 'discipline', $id, 'updated', 'Disciplina actualizada');
            app_redirect('../admin-dashboard.php#disciplinas', 'Disciplina actualizada');
            break;

        case 'servicio':
            $id = request_query_int('id_servicio');
            $current = fetch_one($pdo->prepare('SELECT img_servicio FROM servicios WHERE id_servicio = :id'), array(':id' => $id));
            if ($current === null) {
                throw new InvalidArgumentException('Servicio no encontrado');
            }

            $imagen = save_service_image($_FILES['img_servicio'] ?? array(), (string) $current['img_servicio']);

            $pdo->prepare(
                'UPDATE servicios
                 SET id_disciplina = :id_disciplina, nombre_servicio = :nombre, descripcion_servicio = :descripcion,
                     precio_servicio = :precio, duracion_minutos = :duracion, modalidad_servicio = :modalidad,
                     img_servicio = :imagen, color = :color, textColor = :textColor, activo = :activo,
                     buffer_before_min = :buffer_before_min, buffer_after_min = :buffer_after_min, allows_parallel = :allows_parallel
                 WHERE id_servicio = :id'
            )->execute(
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
                    ':buffer_before_min' => max(0, request_post_int('buffer_before_min', false)),
                    ':buffer_after_min' => max(0, request_post_int('buffer_after_min', false)),
                    ':allows_parallel' => request_post_string('allows_parallel', false) === '1' ? 1 : 0,
                    ':id' => $id,
                )
            );

            audit_log($pdo, 'service', $id, 'updated', 'Servicio actualizado');
            app_redirect('../admin-dashboard.php#servicios', 'Servicio actualizado');
            break;

        case 'evento':
            $id = request_query_int('id_evento');
            $idProfessional = request_post_int('professional_id');
            $idCliente = request_post_int('txt_cliente');
            $idServicio = request_post_int('txt_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end, $id, $idServicio);

            $pdo->prepare(
                'UPDATE eventos
                 SET id_professional = :id_professional, id_cliente = :id_cliente, id_servicio = :id_servicio,
                     start = :start, end = :end, notas_reserva = :notas, estado_reserva = :estado
                 WHERE id_evento = :id_evento'
            )->execute(
                array(
                    ':id_professional' => $idProfessional,
                    ':id_cliente' => $idCliente,
                    ':id_servicio' => $idServicio,
                    ':start' => $start,
                    ':end' => $end,
                    ':notas' => request_post_string('notas_reserva', false),
                    ':estado' => validate_reservation_status(request_post_string('estado_reserva', false)),
                    ':id_evento' => $id,
                )
            );

            audit_log($pdo, 'booking', $id, 'updated', 'Reserva actualizada desde administracion');
            queue_event_notifications($pdo, $id, 'actualizacion');
            dispatch_notification_queue($pdo);
            app_redirect('../admin-dashboard.php#reservas', 'Reserva actualizada');
            break;

        default:
            app_redirect('../index.php');
    }
} catch (Throwable $exception) {
    handle_app_exception($exception, '../admin-dashboard.php');
}
