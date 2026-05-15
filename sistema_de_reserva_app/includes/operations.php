<?php

declare(strict_types=1);

function reservation_status_map(): array
{
    return array(
        'pendiente' => array('label' => 'Pendiente', 'tone' => 'amber'),
        'confirmada' => array('label' => 'Confirmada', 'tone' => 'emerald'),
        'en_progreso' => array('label' => 'En progreso', 'tone' => 'sky'),
        'completada' => array('label' => 'Completada', 'tone' => 'slate'),
        'no_asistio' => array('label' => 'No asistio', 'tone' => 'rose'),
        'cancelada' => array('label' => 'Cancelada', 'tone' => 'zinc'),
    );
}

function waitlist_status_map(): array
{
    return array(
        'pendiente' => 'Pendiente',
        'notificado' => 'Notificado',
        'convertido' => 'Convertido',
        'cerrado' => 'Cerrado',
    );
}

function validate_reservation_status(string $status): string
{
    $status = trim($status);
    if ($status === '') {
        return 'confirmada';
    }

    $statuses = reservation_status_map();
    if (!isset($statuses[$status])) {
        throw new InvalidArgumentException('Estado de reserva no valido');
    }

    return $status;
}

function setting_flag(string $key, bool $default = false): bool
{
    return in_array(strtolower(setting_value($key, $default ? '1' : '0')), array('1', 'true', 'si', 'yes', 'on'), true);
}

function current_actor_context(): array
{
    if (!empty($_SESSION['id_admin']) && (string) ($_SESSION['id_estado'] ?? '') === '1') {
        return array('type' => 'admin', 'id' => (int) $_SESSION['id_admin']);
    }
    if (!empty($_SESSION['id_professional']) && (string) ($_SESSION['id_estado'] ?? '') === '2') {
        return array('type' => 'professional', 'id' => (int) $_SESSION['id_professional']);
    }
    if (!empty($_SESSION['id_cliente']) && (string) ($_SESSION['id_estado'] ?? '') === '3') {
        return array('type' => 'customer', 'id' => (int) $_SESSION['id_cliente']);
    }

    return array('type' => 'system', 'id' => 0);
}

function audit_log(PDO $pdo, string $entityType, int $entityId, string $action, string $summary, array $payload = array()): void
{
    $actor = current_actor_context();
    $pdo->prepare(
        'INSERT INTO audit_log (actor_type, actor_id, entity_type, entity_id, action, summary, payload_json)
         VALUES (:actor_type, :actor_id, :entity_type, :entity_id, :action, :summary, :payload_json)'
    )->execute(
        array(
            ':actor_type' => $actor['type'],
            ':actor_id' => $actor['id'],
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':action' => $action,
            ':summary' => $summary,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        )
    );
}

function service_booking_policy(PDO $pdo, int $serviceId): array
{
    $service = fetch_one(
        $pdo->prepare(
            'SELECT id_servicio, nombre_servicio, duracion_minutos, buffer_before_min, buffer_after_min, allows_parallel
             FROM servicios WHERE id_servicio = :id_servicio'
        ),
        array(':id_servicio' => $serviceId)
    );

    if ($service === null) {
        throw new InvalidArgumentException('Servicio no encontrado');
    }

    return array(
        'id_servicio' => (int) $service['id_servicio'],
        'nombre_servicio' => (string) $service['nombre_servicio'],
        'duracion_minutos' => max(15, (int) $service['duracion_minutos']),
        'buffer_before_min' => max(0, (int) ($service['buffer_before_min'] ?? 0)),
        'buffer_after_min' => max(0, (int) ($service['buffer_after_min'] ?? 0)),
        'allows_parallel' => (int) ($service['allows_parallel'] ?? 0) === 1,
    );
}

function professional_capacity(PDO $pdo, int $professionalId): int
{
    $row = fetch_one(
        $pdo->prepare('SELECT booking_capacity FROM professionals WHERE id_professional = :id_professional'),
        array(':id_professional' => $professionalId)
    );

    return max(1, (int) ($row['booking_capacity'] ?? 1));
}

function professional_accepts_waitlist(PDO $pdo, int $professionalId): bool
{
    $row = fetch_one(
        $pdo->prepare('SELECT accepts_waitlist FROM professionals WHERE id_professional = :id_professional'),
        array(':id_professional' => $professionalId)
    );

    return (int) ($row['accepts_waitlist'] ?? 1) === 1;
}

function global_holiday_for_date(PDO $pdo, string $date): ?array
{
    return fetch_one(
        $pdo->prepare('SELECT * FROM global_holidays WHERE holiday_date = :holiday_date'),
        array(':holiday_date' => $date)
    );
}

function holiday_background_events(PDO $pdo, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
{
    $events = array();
    $cursor = $rangeStart->setTime(0, 0);
    $last = $rangeEnd->setTime(0, 0);
    $hours = business_hours();

    while ($cursor <= $last) {
        $date = $cursor->format('Y-m-d');
        $holiday = global_holiday_for_date($pdo, $date);
        if ($holiday === null) {
            $cursor = $cursor->modify('+1 day');
            continue;
        }

        $startTime = ($holiday['start_time'] ?? '') !== '' ? (string) $holiday['start_time'] : $hours['opening'];
        $endTime = ($holiday['end_time'] ?? '') !== '' ? (string) $holiday['end_time'] : $hours['closing'];
        if ((int) ($holiday['is_closed'] ?? 1) === 1) {
            $startTime = $hours['opening'];
            $endTime = $hours['closing'];
        }

        $events[] = array(
            'id' => 'holiday-' . $date,
            'start' => $date . 'T' . $startTime . ':00',
            'end' => $date . 'T' . $endTime . ':00',
            'display' => 'background',
            'className' => array('holiday-block'),
            'backgroundColor' => 'rgba(239, 68, 68, 0.12)',
            'extendedProps' => array(
                'kind' => 'holiday',
                'holiday_name' => (string) $holiday['holiday_name'],
                'notes' => (string) ($holiday['notes'] ?? ''),
            ),
        );

        $cursor = $cursor->modify('+1 day');
    }

    return $events;
}

function overlapping_bookings(PDO $pdo, int $professionalId, string $start, string $end, ?int $excludeEventId = null): array
{
    $sql = 'SELECT eventos.*, servicios.nombre_servicio, servicios.allows_parallel, servicios.buffer_before_min, servicios.buffer_after_min
            FROM eventos
            JOIN servicios ON eventos.id_servicio = servicios.id_servicio
            WHERE eventos.id_professional = :id_professional
              AND eventos.estado_reserva != "cancelada"';

    $params = array(':id_professional' => $professionalId);

    if ($excludeEventId !== null) {
        $sql .= ' AND eventos.id_evento != :id_evento';
        $params[':id_evento'] = $excludeEventId;
    }

    $sql .= ' ORDER BY eventos.start ASC';

    $rows = fetch_all($pdo->prepare($sql), $params);
    $matches = array();
    $globalBuffer = max(0, (int) setting_value('global_buffer_min', '0'));
    $targetStart = new DateTimeImmutable($start);
    $targetEnd = new DateTimeImmutable($end);

    foreach ($rows as $row) {
        $existingStart = (new DateTimeImmutable((string) $row['start']))->modify('-' . ($globalBuffer + max(0, (int) ($row['buffer_before_min'] ?? 0))) . ' minutes');
        $existingEnd = (new DateTimeImmutable((string) $row['end']))->modify('+' . ($globalBuffer + max(0, (int) ($row['buffer_after_min'] ?? 0))) . ' minutes');

        if ($existingStart < $targetEnd && $existingEnd > $targetStart) {
            $matches[] = $row;
        }
    }

    return $matches;
}

function can_book_parallel(PDO $pdo, int $professionalId, int $serviceId, array $overlaps): bool
{
    $service = service_booking_policy($pdo, $serviceId);
    if ($overlaps === array()) {
        return true;
    }

    if (!$service['allows_parallel']) {
        return false;
    }

    foreach ($overlaps as $overlap) {
        if ((int) ($overlap['allows_parallel'] ?? 0) !== 1) {
            return false;
        }
    }

    return count($overlaps) < professional_capacity($pdo, $professionalId);
}

function create_waitlist_request(PDO $pdo, int $customerId, ?int $professionalId, int $serviceId, string $start, string $end, string $notes = ''): int
{
    $pdo->prepare(
        'INSERT INTO waitlist_requests
         (id_cliente, id_professional, id_servicio, requested_start, requested_end, status, notes, updated_at)
         VALUES (:id_cliente, :id_professional, :id_servicio, :requested_start, :requested_end, "pendiente", :notes, CURRENT_TIMESTAMP)'
    )->execute(
        array(
            ':id_cliente' => $customerId,
            ':id_professional' => $professionalId,
            ':id_servicio' => $serviceId,
            ':requested_start' => $start,
            ':requested_end' => $end,
            ':notes' => $notes,
        )
    );

    $waitlistId = (int) $pdo->lastInsertId();
    audit_log($pdo, 'waitlist', $waitlistId, 'created', 'Solicitud agregada a la lista de espera', array(
        'id_cliente' => $customerId,
        'id_professional' => $professionalId,
        'id_servicio' => $serviceId,
        'requested_start' => $start,
        'requested_end' => $end,
    ));

    return $waitlistId;
}

function queue_notification(PDO $pdo, string $channel, string $recipient, string $templateKey, string $subject, string $message, array $payload = array(), ?int $relatedEventId = null, ?string $scheduledFor = null): void
{
    $pdo->prepare(
        'INSERT INTO notification_log
         (channel, recipient, template_key, subject, message_body, payload_json, related_event_id, status, scheduled_for)
         VALUES (:channel, :recipient, :template_key, :subject, :message_body, :payload_json, :related_event_id, "queued", :scheduled_for)'
    )->execute(
        array(
            ':channel' => $channel,
            ':recipient' => $recipient,
            ':template_key' => $templateKey,
            ':subject' => $subject,
            ':message_body' => $message,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':related_event_id' => $relatedEventId,
            ':scheduled_for' => $scheduledFor ?? (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        )
    );
}

function reservation_notification_recipients(PDO $pdo, int $eventId): array
{
    $event = fetch_one(
        $pdo->prepare(
            'SELECT eventos.*, clientes.nombre_cliente, clientes.apellido_cliente, clientes.correo_cliente, clientes.telefono_cliente,
                    professionals.name_professional, professionals.notification_email, professionals.notification_whatsapp,
                    servicios.nombre_servicio
             FROM eventos
             JOIN clientes ON eventos.id_cliente = clientes.id_cliente
             JOIN professionals ON eventos.id_professional = professionals.id_professional
             JOIN servicios ON eventos.id_servicio = servicios.id_servicio
             WHERE eventos.id_evento = :id_evento'
        ),
        array(':id_evento' => $eventId)
    );

    if ($event === null) {
        throw new InvalidArgumentException('Reserva no encontrada para notificacion');
    }

    return $event;
}

function queue_event_notifications(PDO $pdo, int $eventId, string $templateKey): void
{
    $event = reservation_notification_recipients($pdo, $eventId);
    $start = new DateTimeImmutable((string) $event['start']);
    $subject = 'Reserva ' . strtolower(str_replace('_', ' ', $templateKey));
    $message = sprintf(
        '%s para %s con %s el %s.',
        ucfirst(str_replace('_', ' ', $templateKey)),
        (string) $event['nombre_servicio'],
        (string) $event['name_professional'],
        $start->format('d-m-Y H:i')
    );
    $payload = array(
        'event_id' => $eventId,
        'event_start' => $start->format('c'),
        'customer_name' => trim((string) $event['nombre_cliente'] . ' ' . (string) $event['apellido_cliente']),
        'service_name' => (string) $event['nombre_servicio'],
        'professional_name' => (string) $event['name_professional'],
    );

    if (setting_flag('notifications_email_enabled', true) && (string) $event['correo_cliente'] !== '') {
        queue_notification($pdo, 'email', (string) $event['correo_cliente'], $templateKey, $subject, $message, $payload, $eventId);
    }

    if (setting_flag('notifications_whatsapp_enabled', false) && (string) $event['telefono_cliente'] !== '') {
        $phone = preg_replace('/[^0-9]/', '', (string) $event['telefono_cliente']) ?: '';
        $payload['whatsapp_url'] = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
        queue_notification($pdo, 'whatsapp', (string) $event['telefono_cliente'], $templateKey, $subject, $message, $payload, $eventId);
    }
}

function queue_event_reminders(PDO $pdo): void
{
    $reminderHours = max(1, (int) setting_value('reminder_hours_before', '24'));
    $startWindow = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $endWindow = (new DateTimeImmutable('now +' . $reminderHours . ' hours'))->format('Y-m-d H:i:s');

    $events = fetch_all(
        $pdo->prepare(
            'SELECT id_evento FROM eventos
             WHERE estado_reserva IN ("pendiente", "confirmada")
               AND start BETWEEN :start_window AND :end_window
               AND id_evento NOT IN (
                   SELECT related_event_id FROM notification_log WHERE template_key = "recordatorio" AND status IN ("queued", "sent", "simulated")
               )'
        ),
        array(':start_window' => $startWindow, ':end_window' => $endWindow)
    );

    foreach ($events as $event) {
        queue_event_notifications($pdo, (int) $event['id_evento'], 'recordatorio');
    }
}

function dispatch_notification_queue(PDO $pdo): array
{
    queue_event_reminders($pdo);
    $notifications = fetch_all(
        $pdo->prepare(
            'SELECT * FROM notification_log
             WHERE status = "queued" AND scheduled_for <= :now
             ORDER BY scheduled_for ASC LIMIT 50'
        ),
        array(':now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'))
    );

    $processed = array('sent' => 0, 'simulated' => 0, 'failed' => 0);
    $smtp = smtp_config();
    foreach ($notifications as $notification) {
        $status = 'simulated';
        try {
            if ((string) $notification['channel'] === 'email' && setting_flag('notifications_send_email', false)) {
                if (!smtp_is_configured($smtp)) {
                    throw new RuntimeException('SMTP no configurado');
                }
                send_email_via_smtp($smtp, (string) $notification['recipient'], (string) $notification['subject'], (string) $notification['message_body']);
                $status = 'sent';
            }
        } catch (Throwable $exception) {
            $status = 'failed';
            $payload = json_decode((string) $notification['payload_json'], true);
            if (!is_array($payload)) {
                $payload = array();
            }
            $payload['delivery_error'] = $exception->getMessage();
            $pdo->prepare('UPDATE notification_log SET payload_json = :payload_json WHERE id_notification = :id_notification')
                ->execute(array(
                    ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':id_notification' => (int) $notification['id_notification'],
                ));
        }

        $pdo->prepare(
            'UPDATE notification_log
             SET status = :status, delivered_at = CASE WHEN :status IN ("sent", "simulated") THEN CURRENT_TIMESTAMP ELSE delivered_at END
             WHERE id_notification = :id_notification'
        )->execute(
            array(
                ':status' => $status,
                ':id_notification' => (int) $notification['id_notification'],
            )
        );
        $processed[$status] = ($processed[$status] ?? 0) + 1;
    }

    return $processed;
}

function smtp_config(): array
{
    $env = static fn (string $key, string $fallback = ''): string => (string) (getenv($key) !== false ? getenv($key) : setting_value($key, $fallback));

    return array(
        'host' => $env('SMTP_HOST', setting_value('smtp_host', '')),
        'port' => (int) $env('SMTP_PORT', setting_value('smtp_port', '587')),
        'username' => $env('SMTP_USERNAME', setting_value('smtp_username', '')),
        'password' => $env('SMTP_PASSWORD', setting_value('smtp_password', '')),
        'encryption' => strtolower($env('SMTP_ENCRYPTION', setting_value('smtp_encryption', 'tls'))),
        'from_name' => $env('SMTP_FROM_NAME', setting_value('smtp_from_name', app_brand_name())),
        'from_email' => $env('SMTP_FROM_EMAIL', setting_value('smtp_from_email', setting_value('contact_email', 'contacto@sistema.local'))),
    );
}

function smtp_is_configured(array $config): bool
{
    return $config['host'] !== '' && $config['port'] > 0 && $config['from_email'] !== '';
}

function smtp_read_line($socket): string
{
    $line = '';
    while (!feof($socket)) {
        $chunk = fgets($socket, 515);
        if ($chunk === false) {
            break;
        }
        $line .= $chunk;
        if (strlen($chunk) < 4 || $chunk[3] === ' ') {
            break;
        }
    }

    return $line;
}

function smtp_expect($socket, array $allowedCodes): void
{
    $response = smtp_read_line($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $allowedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }
}

function smtp_command($socket, string $command, array $allowedCodes): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $allowedCodes);
}

function send_email_via_smtp(array $config, string $toEmail, string $subject, string $message): bool
{
    $transport = $config['encryption'] === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client(
        $transport . $config['host'] . ':' . $config['port'],
        $errorNumber,
        $errorMessage,
        15,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException('No se pudo conectar al SMTP: ' . $errorMessage);
    }

    stream_set_timeout($socket, 15);
    smtp_expect($socket, array(220));
    smtp_command($socket, 'EHLO sistema_de_reserva.local', array(250));

    if ($config['encryption'] === 'tls') {
        smtp_command($socket, 'STARTTLS', array(220));
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('No se pudo iniciar STARTTLS');
        }
        smtp_command($socket, 'EHLO sistema_de_reserva.local', array(250));
    }

    if ($config['username'] !== '') {
        smtp_command($socket, 'AUTH LOGIN', array(334));
        smtp_command($socket, base64_encode($config['username']), array(334));
        smtp_command($socket, base64_encode($config['password']), array(235));
    }

    smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', array(250));
    smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', array(250, 251));
    smtp_command($socket, 'DATA', array(354));

    $headers = array(
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'To: <' . $toEmail . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    );
    $body = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $message) . "\r\n.";
    fwrite($socket, $body . "\r\n");
    smtp_expect($socket, array(250));
    smtp_command($socket, 'QUIT', array(221));
    fclose($socket);

    return true;
}

function waitlist_matches_for_event(PDO $pdo, array $event): array
{
    return fetch_all(
        $pdo->prepare(
            'SELECT waitlist_requests.*, clientes.nombre_cliente, clientes.apellido_cliente, clientes.correo_cliente, clientes.telefono_cliente
             FROM waitlist_requests
             JOIN clientes ON waitlist_requests.id_cliente = clientes.id_cliente
             WHERE waitlist_requests.status = "pendiente"
               AND waitlist_requests.id_servicio = :id_servicio
               AND (waitlist_requests.id_professional IS NULL OR waitlist_requests.id_professional = :id_professional)
               AND waitlist_requests.requested_start <= :event_end
               AND waitlist_requests.requested_end >= :event_start
             ORDER BY waitlist_requests.created_at ASC'
        ),
        array(
            ':id_servicio' => $event['id_servicio'],
            ':id_professional' => $event['id_professional'],
            ':event_start' => $event['start'],
            ':event_end' => $event['end'],
        )
    );
}

function notify_waitlist_matches(PDO $pdo, array $event): void
{
    foreach (waitlist_matches_for_event($pdo, $event) as $match) {
        $message = 'Se libero un horario compatible para el servicio solicitado el ' . (new DateTimeImmutable((string) $event['start']))->format('d-m-Y H:i') . '.';
        if ((string) $match['correo_cliente'] !== '') {
            queue_notification($pdo, 'email', (string) $match['correo_cliente'], 'waitlist_match', 'Horario disponible', $message, array(
                'waitlist_id' => (int) $match['id_waitlist'],
                'event_id' => (int) $event['id_evento'],
            ), (int) $event['id_evento']);
        }
        if ((string) $match['telefono_cliente'] !== '') {
            queue_notification($pdo, 'whatsapp', (string) $match['telefono_cliente'], 'waitlist_match', 'Horario disponible', $message, array(
                'waitlist_id' => (int) $match['id_waitlist'],
                'event_id' => (int) $event['id_evento'],
            ), (int) $event['id_evento']);
        }

        $pdo->prepare('UPDATE waitlist_requests SET status = "notificado", updated_at = CURRENT_TIMESTAMP WHERE id_waitlist = :id_waitlist')
            ->execute(array(':id_waitlist' => (int) $match['id_waitlist']));
    }
}

function format_csv_cell(string $value): string
{
    return '"' . str_replace('"', '""', $value) . '"';
}

function export_rows_as_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('No se pudo abrir la salida CSV');
    }

    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function build_simple_pdf(string $title, array $lines): string
{
    $escaped = static function (string $text): string {
        return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
    };
    $truncate = static function (string $text, int $length): string {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length);
        }

        return substr($text, 0, $length);
    };

    $content = "BT\n/F1 14 Tf\n50 790 Td (" . $escaped($title) . ") Tj\n/F1 10 Tf\n";
    $y = 770;
    foreach ($lines as $line) {
        $content .= "1 0 0 1 50 {$y} Tm (" . $escaped($truncate($line, 110)) . ") Tj\n";
        $y -= 14;
        if ($y < 40) {
            break;
        }
    }
    $content .= "ET";

    $objects = array();
    $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
    $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
    $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
    $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
    $objects[] = '5 0 obj << /Length ' . strlen($content) . " >> stream\n" . $content . "\nendstream endobj";

    $pdf = "%PDF-1.4\n";
    $offsets = array(0);
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= 'xref' . "\n";
    $pdf .= '0 ' . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\n";
    $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}

function send_pdf_download(string $filename, string $title, array $lines): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo build_simple_pdf($title, $lines);
    exit;
}

function backups_directory(): string
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

function create_sqlite_backup(): string
{
    $source = APP_DB_PATH;
    if (!is_file($source)) {
        throw new RuntimeException('Base de datos no encontrada para backup');
    }

    $backupName = 'backup_' . (new DateTimeImmutable())->format('Ymd_His') . '.sqlite';
    $target = backups_directory() . DIRECTORY_SEPARATOR . $backupName;
    if (!copy($source, $target)) {
        throw new RuntimeException('No se pudo crear el backup');
    }

    return $backupName;
}

function restore_sqlite_backup(string $backupName): void
{
    $safeName = basename($backupName);
    $source = backups_directory() . DIRECTORY_SEPARATOR . $safeName;
    if (!is_file($source)) {
        throw new InvalidArgumentException('Backup no encontrado');
    }
    if (!copy($source, APP_DB_PATH)) {
        throw new RuntimeException('No se pudo restaurar el backup');
    }
}
