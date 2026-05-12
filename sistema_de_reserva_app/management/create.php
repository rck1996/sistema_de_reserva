<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');

$pdo = app_pdo();
$accion = $_GET['accion'] ?? 'no';

try {
    switch ($accion) {
        case 'profesional':
            $usuario = request_post_string('user_professional');
            $nombre = request_post_string('name_professional');
            $password = hash_password_value(request_post_string('pass_professional'));
            $email = validate_email_address(request_post_string('email_professional'));
            $telefono = validate_phone(request_post_string('phone_professional'));
            $bio = request_post_string('bio_professional', false);
            $color = validate_color(request_post_string('calendar_color'));
            $disciplina = request_post_string('id_disciplina', false);

            ensure_unique_value($pdo, 'professionals', 'user_professional', $usuario);
            ensure_unique_value($pdo, 'professionals', 'email_professional', $email);

            $pdo->prepare(
                'INSERT INTO professionals
                 (user_professional, pass_professional, name_professional, email_professional, phone_professional, bio_professional, calendar_color, id_disciplina, activo, id_estado)
                 VALUES (:usuario, :password, :nombre, :email, :telefono, :bio, :color, :id_disciplina, 1, 2)'
            )->execute(
                array(
                    ':usuario' => $usuario,
                    ':password' => $password,
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':telefono' => $telefono,
                    ':bio' => $bio,
                    ':color' => $color,
                    ':id_disciplina' => $disciplina === '' ? null : (int) $disciplina,
                )
            );

            ensure_professional_schedule_rows($pdo, (int) $pdo->lastInsertId());

            app_redirect('../admin-dashboard.php#profesionales', 'Profesional creado correctamente');
            break;

        case 'cliente':
            $nombre = request_post_string('nombre_cliente');
            $apellido = request_post_string('apellido_cliente');
            $telefono = validate_phone(request_post_string('telefono_cliente'));
            $correo = validate_email_address(request_post_string('correo_cliente'));
            $usuario = request_post_string('user_cliente', false);
            $passwordPlain = request_post_string('pass_cliente', false);
            $notas = request_post_string('notas_cliente', false);

            if ($usuario === '') {
                $usuario = generate_username_from_email($correo);
            }
            if ($passwordPlain === '') {
                $passwordPlain = 'Temporal123';
            }

            ensure_unique_value($pdo, 'clientes', 'correo_cliente', $correo);

            $candidate = $usuario;
            $suffix = 1;
            while (true) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE user_cliente = :user_cliente');
                $stmt->execute(array(':user_cliente' => $candidate));
                if ((int) $stmt->fetchColumn() === 0) {
                    $usuario = $candidate;
                    break;
                }
                $candidate = $usuario . $suffix;
                $suffix++;
            }

            $pdo->prepare(
                'INSERT INTO clientes
                 (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, notas_cliente, id_estado)
                 VALUES (:nombre, :apellido, :telefono, :correo, :usuario, :password, :notas, 3)'
            )->execute(
                array(
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':telefono' => $telefono,
                    ':correo' => $correo,
                    ':usuario' => $usuario,
                    ':password' => hash_password_value($passwordPlain),
                    ':notas' => $notas,
                )
            );

            app_redirect('../admin-dashboard.php#clientes', 'Cliente creado. Usuario: ' . $usuario);
            break;

        case 'disciplina':
            $pdo->prepare(
                'INSERT INTO disciplinas (nombre_disciplina, descripcion_disciplina, color_disciplina, activa)
                 VALUES (:nombre, :descripcion, :color, 1)'
            )->execute(
                array(
                    ':nombre' => request_post_string('nombre_disciplina'),
                    ':descripcion' => request_post_string('descripcion_disciplina', false),
                    ':color' => validate_color(request_post_string('color_disciplina')),
                )
            );

            app_redirect('../admin-dashboard.php#disciplinas', 'Disciplina creada correctamente');
            break;

        case 'servicio':
            $imagen = save_service_image($_FILES['img_servicio'] ?? array());
            $pdo->prepare(
                'INSERT INTO servicios (id_disciplina, nombre_servicio, descripcion_servicio, precio_servicio, duracion_minutos, modalidad_servicio, img_servicio, color, textColor, activo)
                 VALUES (:id_disciplina, :nombre, :descripcion, :precio, :duracion, :modalidad, :imagen, :color, :textColor, 1)'
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
                )
            );

            app_redirect('../admin-dashboard.php#servicios', 'Servicio creado correctamente');
            break;

        case 'agendar_admin':
            $idCliente = request_post_int('txt_cliente');
            $idProfessional = request_post_int('professional_id');
            $idServicio = request_post_int('txt_servicio');
            $start = validate_datetime_slot(request_post_string('dia'), request_post_string('hora'));
            $duration = service_duration_minutes($pdo, $idServicio);
            $end = calculate_event_end($start, $duration);
            ensure_slot_available($pdo, $idProfessional, $start, $end);

            $pdo->prepare(
                'INSERT INTO eventos (title, id_cliente, id_professional, id_servicio, start, end, notas_reserva)
                 VALUES (:title, :id_cliente, :id_professional, :id_servicio, :start, :end, :notas)'
            )->execute(
                array(
                    ':title' => 'Reservado',
                    ':id_cliente' => $idCliente,
                    ':id_professional' => $idProfessional,
                    ':id_servicio' => $idServicio,
                    ':start' => $start,
                    ':end' => $end,
                    ':notas' => request_post_string('notas_reserva', false),
                )
            );

            app_redirect('../admin-dashboard.php#reservas', 'Reserva agregada correctamente');
            break;

        case 'configuracion':
            $keys = array(
                'app_name', 'business_name', 'business_tagline', 'hero_title', 'hero_subtitle',
                'primary_color', 'secondary_color', 'accent_color', 'surface_color',
                'contact_email', 'contact_phone', 'contact_address', 'business_city',
                'business_type', 'opening_time', 'closing_time', 'slot_interval', 'booking_notice'
            );

            foreach ($keys as $key) {
                $value = request_post_string($key, false);
                if (str_contains($key, 'color') && $value !== '') {
                    $value = validate_color($value);
                }
                save_setting($pdo, $key, $value);
            }

            save_setting(
                $pdo,
                'brand_logo',
                save_brand_asset($_FILES['brand_logo'] ?? array(), 'logo', array('png', 'jpg', 'jpeg', 'webp', 'svg'), array('image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'), setting_value('brand_logo', ''))
            );
            save_setting(
                $pdo,
                'brand_favicon',
                save_brand_asset($_FILES['brand_favicon'] ?? array(), 'favicon', array('png', 'ico'), array('image/png', 'image/x-icon', 'image/vnd.microsoft.icon'), setting_value('brand_favicon', ''))
            );
            save_setting(
                $pdo,
                'brand_cover',
                save_brand_asset($_FILES['brand_cover'] ?? array(), 'cover', array('png', 'jpg', 'jpeg', 'webp'), array('image/png', 'image/jpeg', 'image/webp'), setting_value('brand_cover', ''))
            );

            app_redirect('../admin-dashboard.php#configuracion', 'Configuracion actualizada');
            break;

        default:
            app_redirect('../index.php');
    }
} catch (Throwable $exception) {
    handle_app_exception($exception, '../admin-dashboard.php');
}
