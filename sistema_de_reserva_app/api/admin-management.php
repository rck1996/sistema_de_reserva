<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['id_admin']) || (string) ($_SESSION['id_estado'] ?? '') !== '1') {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'Sesion de administrador requerida'));
    exit;
}

$pdo = app_pdo();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $action = request_post_string('action');

        switch ($action) {
            case 'settings':
                $keys = array(
                    'app_name', 'business_name', 'business_tagline', 'hero_title', 'hero_subtitle',
                    'contact_email', 'contact_phone', 'business_type', 'opening_time', 'closing_time',
                    'slot_interval', 'booking_notice', 'global_buffer_min', 'reminder_hours_before',
                    'notifications_email_enabled', 'notifications_whatsapp_enabled', 'app_timezone'
                );

                foreach ($keys as $key) {
                    save_setting($pdo, $key, request_post_string($key, false));
                }

                audit_log($pdo, 'settings', 1, 'updated', 'Configuracion actualizada desde React');
                json_success($pdo);
                break;

            case 'client':
                $id = request_post_int('id_cliente', false);
                $firstName = request_post_string('nombre_cliente');
                $lastName = request_post_string('apellido_cliente');
                $phone = validate_phone(request_post_string('telefono_cliente'));
                $email = validate_email_address(request_post_string('correo_cliente'));
                $username = request_post_string('user_cliente', false);
                $notes = request_post_string('notas_cliente', false);

                if ($username === '') {
                    $username = generate_username_from_email($email);
                }

                if ($id > 0) {
                    ensure_unique_value($pdo, 'clientes', 'correo_cliente', $email, $id, 'id_cliente');
                    ensure_unique_value($pdo, 'clientes', 'user_cliente', $username, $id, 'id_cliente');
                    $pdo->prepare(
                        'UPDATE clientes
                         SET nombre_cliente = :first_name, apellido_cliente = :last_name, telefono_cliente = :phone,
                             correo_cliente = :email, user_cliente = :username, notas_cliente = :notes
                         WHERE id_cliente = :id'
                    )->execute(array(
                        ':first_name' => $firstName,
                        ':last_name' => $lastName,
                        ':phone' => $phone,
                        ':email' => $email,
                        ':username' => $username,
                        ':notes' => $notes,
                        ':id' => $id,
                    ));
                    audit_log($pdo, 'customer', $id, 'updated', 'Cliente actualizado desde React');
                } else {
                    ensure_unique_value($pdo, 'clientes', 'correo_cliente', $email);
                    $candidate = $username;
                    $suffix = 1;
                    while ((int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM clientes WHERE user_cliente = :value', $candidate) > 0) {
                        $candidate = $username . $suffix;
                        $suffix++;
                    }
                    $username = $candidate;
                    $password = request_post_string('pass_cliente', false);
                    if ($password === '') {
                        $password = 'Temporal123';
                    }

                    $pdo->prepare(
                        'INSERT INTO clientes (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, notas_cliente, id_estado)
                         VALUES (:first_name, :last_name, :phone, :email, :username, :password, :notes, 3)'
                    )->execute(array(
                        ':first_name' => $firstName,
                        ':last_name' => $lastName,
                        ':phone' => $phone,
                        ':email' => $email,
                        ':username' => $username,
                        ':password' => hash_password_value($password),
                        ':notes' => $notes,
                    ));
                    audit_log($pdo, 'customer', (int) $pdo->lastInsertId(), 'created', 'Cliente creado desde React');
                }

                json_success($pdo);
                break;

            case 'discipline':
                $id = request_post_int('id_disciplina', false);
                $name = request_post_string('nombre_disciplina');
                $description = request_post_string('descripcion_disciplina', false);
                $color = validate_color(request_post_string('color_disciplina', false) ?: '#22d3ee');
                $active = request_post_string('activa', false) === '0' ? 0 : 1;

                if ($id > 0) {
                    $pdo->prepare(
                        'UPDATE disciplinas SET nombre_disciplina = :name, descripcion_disciplina = :description, color_disciplina = :color, activa = :active WHERE id_disciplina = :id'
                    )->execute(array(':name' => $name, ':description' => $description, ':color' => $color, ':active' => $active, ':id' => $id));
                    audit_log($pdo, 'discipline', $id, 'updated', 'Disciplina actualizada desde React');
                } else {
                    $pdo->prepare(
                        'INSERT INTO disciplinas (nombre_disciplina, descripcion_disciplina, color_disciplina, activa) VALUES (:name, :description, :color, 1)'
                    )->execute(array(':name' => $name, ':description' => $description, ':color' => $color));
                    audit_log($pdo, 'discipline', (int) $pdo->lastInsertId(), 'created', 'Disciplina creada desde React');
                }

                json_success($pdo);
                break;

            case 'service':
                $id = request_post_int('id_servicio', false);
                $disciplineId = request_post_int('id_disciplina', false);
                $name = request_post_string('nombre_servicio');
                $description = request_post_string('descripcion_servicio', false);
                $price = (float) request_post_string('precio_servicio', false);
                $duration = max(15, request_post_int('duracion_minutos', false) ?: 60);
                $modality = request_post_string('modalidad_servicio', false) ?: 'Presencial';
                $color = validate_color(request_post_string('color', false) ?: '#22d3ee');
                $active = request_post_string('activo', false) === '0' ? 0 : 1;

                if ($id > 0) {
                    $current = fetch_one($pdo->prepare('SELECT img_servicio FROM servicios WHERE id_servicio = :id'), array(':id' => $id));
                    if ($current === null) {
                        throw new InvalidArgumentException('Servicio no encontrado');
                    }
                    $pdo->prepare(
                        'UPDATE servicios
                         SET id_disciplina = :discipline, nombre_servicio = :name, descripcion_servicio = :description,
                             precio_servicio = :price, duracion_minutos = :duration, modalidad_servicio = :modality,
                             color = :color, textColor = "#ffffff", activo = :active
                         WHERE id_servicio = :id'
                    )->execute(array(
                        ':discipline' => $disciplineId > 0 ? $disciplineId : null,
                        ':name' => $name,
                        ':description' => $description,
                        ':price' => $price,
                        ':duration' => $duration,
                        ':modality' => $modality,
                        ':color' => $color,
                        ':active' => $active,
                        ':id' => $id,
                    ));
                    audit_log($pdo, 'service', $id, 'updated', 'Servicio actualizado desde React');
                } else {
                    $pdo->prepare(
                        'INSERT INTO servicios (id_disciplina, nombre_servicio, descripcion_servicio, precio_servicio, duracion_minutos, modalidad_servicio, img_servicio, color, textColor, activo)
                         VALUES (:discipline, :name, :description, :price, :duration, :modality, "2.jpg", :color, "#ffffff", 1)'
                    )->execute(array(
                        ':discipline' => $disciplineId > 0 ? $disciplineId : null,
                        ':name' => $name,
                        ':description' => $description,
                        ':price' => $price,
                        ':duration' => $duration,
                        ':modality' => $modality,
                        ':color' => $color,
                    ));
                    audit_log($pdo, 'service', (int) $pdo->lastInsertId(), 'created', 'Servicio creado desde React');
                }

                json_success($pdo);
                break;

            default:
                throw new InvalidArgumentException('Accion no soportada');
        }
        exit;
    }

    json_success($pdo);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => $exception->getMessage(), 'csrfToken' => csrf_token()));
}

function json_success(PDO $pdo): void
{
    echo json_encode(array(
        'ok' => true,
        'csrfToken' => csrf_token(),
        'settings' => management_settings_payload(),
        'disciplines' => fetch_all($pdo->prepare(
            'SELECT id_disciplina AS id, nombre_disciplina AS name, descripcion_disciplina AS description, color_disciplina AS color, activa AS active
             FROM disciplinas ORDER BY nombre_disciplina'
        )),
        'services' => fetch_all($pdo->prepare(
            'SELECT servicios.id_servicio AS id, servicios.id_disciplina AS disciplineId, servicios.nombre_servicio AS name,
                    servicios.descripcion_servicio AS description, servicios.precio_servicio AS price,
                    servicios.duracion_minutos AS durationMinutes, servicios.modalidad_servicio AS modality,
                    servicios.color, servicios.activo AS active, disciplinas.nombre_disciplina AS disciplineName
             FROM servicios
             LEFT JOIN disciplinas ON disciplinas.id_disciplina = servicios.id_disciplina
             ORDER BY servicios.nombre_servicio'
        )),
        'customers' => fetch_all($pdo->prepare(
            'SELECT id_cliente AS id, nombre_cliente AS firstName, apellido_cliente AS lastName,
                    telefono_cliente AS phone, correo_cliente AS email, user_cliente AS username,
                    notas_cliente AS notes, id_estado AS statusId
             FROM clientes ORDER BY nombre_cliente, apellido_cliente'
        )),
        'professionals' => fetch_all($pdo->prepare(
            'SELECT professionals.id_professional AS id, professionals.name_professional AS name, professionals.user_professional AS username,
                    professionals.email_professional AS email, professionals.phone_professional AS phone, professionals.bio_professional AS bio,
                    professionals.calendar_color AS color, professionals.activo AS active, professionals.booking_capacity AS capacity,
                    GROUP_CONCAT(DISTINCT professional_services.id_servicio) AS serviceIds,
                    GROUP_CONCAT(DISTINCT professional_disciplines.id_disciplina) AS disciplineIds
             FROM professionals
             LEFT JOIN professional_services ON professional_services.id_professional = professionals.id_professional
             LEFT JOIN professional_disciplines ON professional_disciplines.id_professional = professionals.id_professional
             GROUP BY professionals.id_professional
             ORDER BY professionals.name_professional'
        )),
    ));
}

function management_settings_payload(): array
{
    $keys = array(
        'app_name', 'business_name', 'business_tagline', 'hero_title', 'hero_subtitle',
        'contact_email', 'contact_phone', 'business_type', 'opening_time', 'closing_time',
        'slot_interval', 'booking_notice', 'global_buffer_min', 'reminder_hours_before',
        'notifications_email_enabled', 'notifications_whatsapp_enabled', 'app_timezone',
    );
    $settings = array();
    foreach ($keys as $key) {
        $settings[$key] = setting_value($key, '');
    }

    return $settings;
}

function fetch_scalar(PDO $pdo, string $sql, string $value): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute(array(':value' => $value));

    return $statement->fetchColumn();
}
