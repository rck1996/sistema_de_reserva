<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const APP_DB_PATH = __DIR__ . '/../data/sistema_de_reserva.sqlite';

function app_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDirectory = dirname(APP_DB_PATH);
    if (!is_dir($dataDirectory)) {
        mkdir($dataDirectory, 0777, true);
    }

    $pdo = new PDO('sqlite:' . APP_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initialize_database($pdo);

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS estado (
            id_estado INTEGER PRIMARY KEY,
            estado TEXT NOT NULL UNIQUE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin (
            id_admin INTEGER PRIMARY KEY AUTOINCREMENT,
            user_admin TEXT NOT NULL UNIQUE,
            pass_admin TEXT NOT NULL,
            id_estado INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (id_estado) REFERENCES estado(id_estado)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS disciplinas (
            id_disciplina INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre_disciplina TEXT NOT NULL UNIQUE,
            descripcion_disciplina TEXT NOT NULL DEFAULT "",
            color_disciplina TEXT NOT NULL DEFAULT "#0f766e",
            activa INTEGER NOT NULL DEFAULT 1
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS professionals (
            id_professional INTEGER PRIMARY KEY AUTOINCREMENT,
            user_professional TEXT NOT NULL UNIQUE,
            pass_professional TEXT NOT NULL,
            name_professional TEXT NOT NULL,
            email_professional TEXT NOT NULL DEFAULT "",
            phone_professional TEXT NOT NULL DEFAULT "",
            bio_professional TEXT NOT NULL DEFAULT "",
            calendar_color TEXT NOT NULL DEFAULT "#0f172a",
            id_disciplina INTEGER,
            activo INTEGER NOT NULL DEFAULT 1,
            id_estado INTEGER NOT NULL DEFAULT 2,
            FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id_disciplina) ON DELETE SET NULL,
            FOREIGN KEY (id_estado) REFERENCES estado(id_estado)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre_cliente TEXT NOT NULL,
            apellido_cliente TEXT NOT NULL,
            telefono_cliente TEXT NOT NULL,
            correo_cliente TEXT NOT NULL UNIQUE,
            user_cliente TEXT NOT NULL UNIQUE,
            pass_cliente TEXT NOT NULL,
            notas_cliente TEXT NOT NULL DEFAULT "",
            id_estado INTEGER NOT NULL DEFAULT 3,
            FOREIGN KEY (id_estado) REFERENCES estado(id_estado)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS servicios (
            id_servicio INTEGER PRIMARY KEY AUTOINCREMENT,
            id_disciplina INTEGER,
            nombre_servicio TEXT NOT NULL,
            descripcion_servicio TEXT NOT NULL,
            precio_servicio REAL NOT NULL,
            duracion_minutos INTEGER NOT NULL DEFAULT 60,
            modalidad_servicio TEXT NOT NULL DEFAULT "Presencial",
            img_servicio TEXT NOT NULL,
            color TEXT NOT NULL,
            textColor TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id_disciplina) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS eventos (
            id_evento INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL DEFAULT "Reservado",
            id_professional INTEGER NOT NULL,
            id_cliente INTEGER NOT NULL,
            id_servicio INTEGER NOT NULL,
            start TEXT NOT NULL,
            end TEXT,
            estado_reserva TEXT NOT NULL DEFAULT "confirmada",
            notas_reserva TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_professional) REFERENCES professionals(id_professional) ON DELETE CASCADE,
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE,
            FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS configuracion (
            clave TEXT PRIMARY KEY,
            valor TEXT NOT NULL
        )'
    );

    migrate_legacy_schema($pdo);

    $statusRows = array(
        array(1, 'Administrador'),
        array(2, 'Profesional'),
        array(3, 'Cliente'),
    );

    $statusStmt = $pdo->prepare('INSERT OR IGNORE INTO estado (id_estado, estado) VALUES (?, ?)');
    foreach ($statusRows as $statusRow) {
        $statusStmt->execute($statusRow);
    }

    $defaultSettings = array(
        'app_name' => 'sistema_de_reserva',
        'business_name' => 'Sistema de Reserva',
        'business_tagline' => 'Reservas online para equipos y servicios multidisciplinarios.',
        'hero_title' => 'Centraliza profesionales, servicios y reservas en un solo sistema.',
        'hero_subtitle' => 'Configura identidad, horarios, disciplinas y disponibilidad desde el panel administrativo.',
        'primary_color' => '#0f766e',
        'secondary_color' => '#0f172a',
        'accent_color' => '#f97316',
        'surface_color' => '#f8fafc',
        'contact_email' => 'contacto@sistema.local',
        'contact_phone' => '+56900000000',
        'contact_address' => 'Configura tu dirección desde el panel',
        'business_city' => 'Santiago de Chile',
        'business_type' => 'Centro de servicios',
        'opening_time' => '09:00',
        'closing_time' => '20:00',
        'slot_interval' => '30',
        'booking_notice' => 'Selecciona disciplina, servicio, profesional y horario disponible.',
    );

    $settingStmt = $pdo->prepare('INSERT OR IGNORE INTO configuracion (clave, valor) VALUES (:clave, :valor)');
    foreach ($defaultSettings as $key => $value) {
        $settingStmt->execute(array(':clave' => $key, ':valor' => $value));
    }

    $defaultDisciplines = array(
        array('Estética', 'Servicios de cuidado personal y belleza.', '#0f766e'),
        array('Bienestar', 'Atenciones enfocadas en relajación y acompañamiento.', '#1d4ed8'),
        array('Asesoría', 'Sesiones profesionales con agenda organizada.', '#7c3aed'),
    );
    $disciplineStmt = $pdo->prepare(
        'INSERT OR IGNORE INTO disciplinas (nombre_disciplina, descripcion_disciplina, color_disciplina)
         VALUES (:nombre, :descripcion, :color)'
    );
    foreach ($defaultDisciplines as $discipline) {
        $disciplineStmt->execute(
            array(
                ':nombre' => $discipline[0],
                ':descripcion' => $discipline[1],
                ':color' => $discipline[2],
            )
        );
    }

    $adminExists = (int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
    if ($adminExists === 0) {
        $stmt = $pdo->prepare('INSERT INTO admin (user_admin, pass_admin, id_estado) VALUES (:user_admin, :pass_admin, 1)');
        $stmt->execute(
            array(
                ':user_admin' => 'admin@sistema.local',
                ':pass_admin' => hash_password_value('Admin12345'),
            )
        );
    }

    $initialized = true;
}

function migrate_legacy_schema(PDO $pdo): void
{
    if (table_exists($pdo, 'peluqueros') && !table_exists($pdo, 'professionals')) {
        $pdo->exec('ALTER TABLE peluqueros RENAME TO professionals');
    }

    rename_column_if_exists($pdo, 'professionals', 'id_peluquero', 'id_professional');
    rename_column_if_exists($pdo, 'professionals', 'user_peluquero', 'user_professional');
    rename_column_if_exists($pdo, 'professionals', 'pass_peluquero', 'pass_professional');
    rename_column_if_exists($pdo, 'professionals', 'nombre_peluquero', 'name_professional');
    rename_column_if_exists($pdo, 'professionals', 'email_peluquero', 'email_professional');
    rename_column_if_exists($pdo, 'professionals', 'telefono_peluquero', 'phone_professional');
    rename_column_if_exists($pdo, 'professionals', 'bio_peluquero', 'bio_professional');
    rename_column_if_exists($pdo, 'professionals', 'color_peluquero', 'calendar_color');

    ensure_column_exists($pdo, 'professionals', 'email_professional', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'professionals', 'phone_professional', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'professionals', 'bio_professional', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'professionals', 'calendar_color', 'TEXT NOT NULL DEFAULT "#0f172a"');
    ensure_column_exists($pdo, 'professionals', 'id_disciplina', 'INTEGER');
    ensure_column_exists($pdo, 'professionals', 'activo', 'INTEGER NOT NULL DEFAULT 1');

    ensure_column_exists($pdo, 'clientes', 'notas_cliente', 'TEXT NOT NULL DEFAULT ""');

    ensure_column_exists($pdo, 'servicios', 'id_disciplina', 'INTEGER');
    ensure_column_exists($pdo, 'servicios', 'duracion_minutos', 'INTEGER NOT NULL DEFAULT 60');
    ensure_column_exists($pdo, 'servicios', 'modalidad_servicio', 'TEXT NOT NULL DEFAULT "Presencial"');
    ensure_column_exists($pdo, 'servicios', 'activo', 'INTEGER NOT NULL DEFAULT 1');

    rename_column_if_exists($pdo, 'eventos', 'id_peluquero', 'id_professional');
    ensure_column_exists($pdo, 'eventos', 'estado_reserva', 'TEXT NOT NULL DEFAULT "confirmada"');
    ensure_column_exists($pdo, 'eventos', 'notas_reserva', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'eventos', 'created_at', 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP');

    $pdo->exec("UPDATE eventos SET end = datetime(start, '+60 minutes') WHERE end IS NULL OR end = ''");
}

function ensure_column_exists(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $existingColumn) {
        if (($existingColumn['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
    $stmt->execute(array(':table' => $table));

    return $stmt->fetchColumn() !== false;
}

function rename_column_if_exists(PDO $pdo, string $table, string $oldColumn, string $newColumn): void
{
    if (!table_exists($pdo, $table)) {
        return;
    }

    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    $hasOld = false;
    $hasNew = false;

    foreach ($columns as $existingColumn) {
        if (($existingColumn['name'] ?? '') === $oldColumn) {
            $hasOld = true;
        }
        if (($existingColumn['name'] ?? '') === $newColumn) {
            $hasNew = true;
        }
    }

    if ($hasOld && !$hasNew) {
        $pdo->exec('ALTER TABLE ' . $table . ' RENAME COLUMN ' . $oldColumn . ' TO ' . $newColumn);
    }
}

function app_settings(PDO $pdo): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $rows = fetch_all($pdo->prepare('SELECT clave, valor FROM configuracion'));
    $settings = array();
    foreach ($rows as $row) {
        $settings[$row['clave']] = $row['valor'];
    }

    return $settings;
}

function setting_value(string $key, string $default = ''): string
{
    $settings = app_settings(app_pdo());

    return isset($settings[$key]) ? (string) $settings[$key] : $default;
}

function save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)
        ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor');
    $stmt->execute(array(':clave' => $key, ':valor' => $value));
}

function app_brand_name(): string
{
    return setting_value('business_name', 'Sistema de Reserva');
}

function app_display_name(): string
{
    return setting_value('app_name', 'sistema_de_reserva');
}

function current_theme(): array
{
    return array(
        'primary' => setting_value('primary_color', '#0f766e'),
        'secondary' => setting_value('secondary_color', '#0f172a'),
        'accent' => setting_value('accent_color', '#f97316'),
        'surface' => setting_value('surface_color', '#f8fafc'),
    );
}

function business_hours(): array
{
    return array(
        'opening' => setting_value('opening_time', '09:00'),
        'closing' => setting_value('closing_time', '20:00'),
        'slot_interval' => (int) setting_value('slot_interval', '30'),
    );
}

function request_post_string(string $key, bool $required = true): string
{
    $value = $_POST[$key] ?? '';
    $value = trim((string) $value);

    if ($required && $value === '') {
        throw new InvalidArgumentException('Falta el campo requerido: ' . $key);
    }

    return $value;
}

function request_query_int(string $key): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    if ($value === false || $value === null) {
        throw new InvalidArgumentException('Parametro invalido: ' . $key);
    }

    return (int) $value;
}

function request_session_int(string $key): int
{
    $value = $_SESSION[$key] ?? null;

    if (!is_numeric($value)) {
        throw new RuntimeException('Sesion invalida');
    }

    return (int) $value;
}

function validate_email_address(string $email): string
{
    $validated = filter_var($email, FILTER_VALIDATE_EMAIL);

    if ($validated === false) {
        throw new InvalidArgumentException('Correo invalido');
    }

    return (string) $validated;
}

function validate_phone(string $phone): string
{
    $normalized = preg_replace('/[^0-9+]/', '', $phone);

    if ($normalized === null || strlen($normalized) < 8 || strlen($normalized) > 15) {
        throw new InvalidArgumentException('Telefono invalido');
    }

    return $normalized;
}

function validate_color(string $color): string
{
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        throw new InvalidArgumentException('Color invalido');
    }

    return $color;
}

function validate_datetime_slot(string $date, string $time): string
{
    $slot = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time);

    if (!$slot) {
        throw new InvalidArgumentException('Fecha u hora invalida');
    }

    $hours = business_hours();
    $hour = $slot->format('H:i');
    if ($hour < $hours['opening'] || $hour >= $hours['closing']) {
        throw new InvalidArgumentException('La hora esta fuera del horario permitido');
    }

    $interval = max(5, (int) $hours['slot_interval']);
    $minutes = (int) $slot->format('i');
    if ($minutes % $interval !== 0) {
        throw new InvalidArgumentException('La hora debe respetar el intervalo de agenda configurado');
    }

    return $slot->format('Y-m-d H:i:s');
}

function hash_password_value(string $password): string
{
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('La contrasena debe tener al menos 8 caracteres');
    }

    return password_hash($password, PASSWORD_DEFAULT);
}

function password_matches(string $password, string $storedHashOrPlain): bool
{
    if ($storedHashOrPlain === '') {
        return false;
    }

    if (is_password_hash($storedHashOrPlain)) {
        return password_verify($password, $storedHashOrPlain);
    }

    return hash_equals($storedHashOrPlain, $password);
}

function password_needs_upgrade(string $storedHashOrPlain): bool
{
    if (!is_password_hash($storedHashOrPlain)) {
        return true;
    }

    return password_needs_rehash($storedHashOrPlain, PASSWORD_DEFAULT);
}

function is_password_hash(string $value): bool
{
    $info = password_get_info($value);

    return !empty($info['algo']);
}

function app_redirect(string $path, string $message = ''): void
{
    if ($message !== '') {
        echo "<script>alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ")</script>";
    }

    echo "<script>location.href=" . json_encode($path) . "</script>";
    exit;
}

function require_role(string $sessionKey, string $expectedRole, string $redirect): void
{
    if (empty($_SESSION[$sessionKey]) || (string) ($_SESSION['id_estado'] ?? '') !== $expectedRole) {
        header('Location: ' . $redirect);
        exit;
    }
}

function escape_html(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function handle_app_exception(Throwable $exception, string $redirect): void
{
    app_redirect($redirect, $exception->getMessage());
}

function save_service_image(array $file, ?string $currentImage = null): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($currentImage !== null && $currentImage !== '') {
            return $currentImage;
        }

        throw new InvalidArgumentException('La imagen del servicio es obligatoria');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No se pudo subir la imagen del servicio');
    }

    $allowedExtensions = array('jpg', 'jpeg', 'png', 'webp');
    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new InvalidArgumentException('Formato de imagen no permitido');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new InvalidArgumentException('El archivo subido no es una imagen valida');
    }

    $allowedMimeTypes = array('image/jpeg', 'image/png', 'image/webp');
    if (!in_array((string) ($imageInfo['mime'] ?? ''), $allowedMimeTypes, true)) {
        throw new InvalidArgumentException('El tipo MIME de la imagen no es valido');
    }

    $safeName = uniqid('servicio_', true) . '.' . $extension;
    $servicesDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'services';
    if (!is_dir($servicesDirectory) && !mkdir($servicesDirectory, 0777, true) && !is_dir($servicesDirectory)) {
        throw new RuntimeException('No se pudo preparar la carpeta de imagenes');
    }

    $destination = $servicesDirectory . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('No se pudo guardar la imagen del servicio');
    }

    if ($currentImage !== null && $currentImage !== '' && $currentImage !== $safeName) {
        $currentPath = $servicesDirectory . DIRECTORY_SEPARATOR . basename($currentImage);
        if (is_file($currentPath)) {
            @unlink($currentPath);
        }
    }

    return $safeName;
}

function ensure_unique_value(PDO $pdo, string $table, string $column, string $value, ?int $excludeId = null, ?string $idColumn = null): void
{
    $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
    $params = array(':value' => $value);

    if ($excludeId !== null && $idColumn !== null) {
        $sql .= " AND {$idColumn} != :exclude_id";
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new InvalidArgumentException('Ya existe un registro con ese valor: ' . $column);
    }
}

function service_duration_minutes(PDO $pdo, int $serviceId): int
{
    $stmt = $pdo->prepare('SELECT duracion_minutos FROM servicios WHERE id_servicio = :id_servicio');
    $row = fetch_one($stmt, array(':id_servicio' => $serviceId));

    if ($row === null) {
        throw new InvalidArgumentException('Servicio no encontrado');
    }

    return max(15, (int) $row['duracion_minutos']);
}

function calculate_event_end(string $start, int $durationMinutes): string
{
    $startDate = new DateTimeImmutable($start);

    return $startDate->modify('+' . $durationMinutes . ' minutes')->format('Y-m-d H:i:s');
}

function ensure_slot_available(PDO $pdo, int $professionalId, string $start, string $end, ?int $excludeEventId = null): void
{
    $sql = 'SELECT COUNT(*) FROM eventos
            WHERE id_professional = :id_professional
              AND estado_reserva != "cancelada"
              AND start < :end
              AND COALESCE(end, datetime(start, "+60 minutes")) > :start';

    $params = array(
        ':id_professional' => $professionalId,
        ':start' => $start,
        ':end' => $end,
    );

    if ($excludeEventId !== null) {
        $sql .= ' AND id_evento != :id_evento';
        $params[':id_evento'] = $excludeEventId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new InvalidArgumentException('Ese profesional ya tiene una reserva en ese rango horario');
    }

    $closing = business_hours()['closing'];
    $endHour = (new DateTimeImmutable($end))->format('H:i');
    if ($endHour > $closing) {
        throw new InvalidArgumentException('La reserva supera el horario de cierre configurado');
    }
}

function generate_username_from_email(string $email, string $fallbackPrefix = 'cliente'): string
{
    $localPart = strstr($email, '@', true);
    $candidate = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string) $localPart));
    $candidate = trim((string) $candidate, '_');

    if ($candidate === '') {
        $candidate = $fallbackPrefix;
    }

    return $candidate;
}

function fetch_all(PDOStatement $stmt, array $params = array()): array
{
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function fetch_one(PDOStatement $stmt, array $params = array()): ?array
{
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}
