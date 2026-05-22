<?php

declare(strict_types=1);

require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/operations.php';
require_once __DIR__ . '/view.php';

load_env_file(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');
date_default_timezone_set((string) (getenv('APP_TIMEZONE') ?: 'America/Santiago'));

configure_session_security();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

enforce_session_timeout();

const APP_DB_PATH = __DIR__ . '/../data/sistema_de_reserva.sqlite';

function configure_session_security(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params(
        array(
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'path' => '/',
        )
    );
}

function enforce_session_timeout(): void
{
    $timeoutSeconds = !empty($_SESSION['remember_me']) ? 2592000 : 7200;
    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);

    if ($lastActivity > 0 && ($now - $lastActivity) > $timeoutSeconds) {
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
    }

    $_SESSION['last_activity_at'] = $now;
}

function persist_session_cookie(bool $remember): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        session_id(),
        array(
            'expires' => $remember ? time() + 2592000 : 0,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?: '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        )
    );
}

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
        'CREATE TABLE IF NOT EXISTS professional_availability (
            id_availability INTEGER PRIMARY KEY AUTOINCREMENT,
            id_professional INTEGER NOT NULL,
            weekday INTEGER NOT NULL,
            is_working INTEGER NOT NULL DEFAULT 1,
            start_time TEXT NOT NULL DEFAULT "09:00",
            end_time TEXT NOT NULL DEFAULT "18:00",
            break_start TEXT NOT NULL DEFAULT "",
            break_end TEXT NOT NULL DEFAULT "",
            slot_interval INTEGER NOT NULL DEFAULT 30,
            UNIQUE(id_professional, weekday),
            FOREIGN KEY (id_professional) REFERENCES professionals(id_professional) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS professional_exceptions (
            id_exception INTEGER PRIMARY KEY AUTOINCREMENT,
            id_professional INTEGER NOT NULL,
            exception_date TEXT NOT NULL,
            is_day_off INTEGER NOT NULL DEFAULT 0,
            start_time TEXT NOT NULL DEFAULT "",
            end_time TEXT NOT NULL DEFAULT "",
            notes TEXT NOT NULL DEFAULT "",
            UNIQUE(id_professional, exception_date),
            FOREIGN KEY (id_professional) REFERENCES professionals(id_professional) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS professional_disciplines (
            id_professional INTEGER NOT NULL,
            id_disciplina INTEGER NOT NULL,
            PRIMARY KEY (id_professional, id_disciplina),
            FOREIGN KEY (id_professional) REFERENCES professionals(id_professional) ON DELETE CASCADE,
            FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id_disciplina) ON DELETE CASCADE
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
        'CREATE TABLE IF NOT EXISTS professional_services (
            id_professional INTEGER NOT NULL,
            id_servicio INTEGER NOT NULL,
            PRIMARY KEY (id_professional, id_servicio),
            FOREIGN KEY (id_professional) REFERENCES professionals(id_professional) ON DELETE CASCADE,
            FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE CASCADE
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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id_token INTEGER PRIMARY KEY AUTOINCREMENT,
            account_type TEXT NOT NULL,
            account_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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
        'contact_address' => 'Configura tu direccion desde el panel',
        'business_city' => 'Santiago de Chile',
        'business_type' => 'Centro de servicios',
        'brand_logo' => '',
        'brand_favicon' => '',
        'brand_cover' => '',
        'opening_time' => '09:00',
        'closing_time' => '20:00',
        'slot_interval' => '30',
        'booking_notice' => 'Selecciona disciplina, servicio, profesional y horario disponible.',
        'global_buffer_min' => '0',
        'reminder_hours_before' => '24',
        'notifications_email_enabled' => '0',
        'notifications_whatsapp_enabled' => '0',
        'notifications_send_email' => '0',
        'smtp_from_name' => 'Sistema de Reserva',
        'smtp_from_email' => 'notificaciones@sistema.local',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'app_timezone' => 'America/Santiago',
    );

    $settingStmt = $pdo->prepare('INSERT OR IGNORE INTO configuracion (clave, valor) VALUES (:clave, :valor)');
    foreach ($defaultSettings as $key => $value) {
        $settingStmt->execute(array(':clave' => $key, ':valor' => $value));
    }

    $defaultDisciplines = array(
        array('Estetica', 'Servicios de cuidado personal y bienestar visual.', '#0f766e'),
        array('Bienestar', 'Atenciones enfocadas en relajacion y acompanamiento.', '#1d4ed8'),
        array('Asesoria', 'Sesiones profesionales con agenda organizada.', '#7c3aed'),
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

    if ((int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO admin (user_admin, pass_admin, id_estado) VALUES (:user_admin, :pass_admin, 1)')
            ->execute(
                array(
                    ':user_admin' => 'admin@sistema.local',
                    ':pass_admin' => hash_password_value('Admin12345'),
                )
            );
    }

    run_registered_migrations($pdo);
    seed_demo_content($pdo);
    ensure_professional_schedules_seeded($pdo);
    ensure_professional_assignments_seeded($pdo);

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
    ensure_column_exists($pdo, 'professionals', 'booking_capacity', 'INTEGER NOT NULL DEFAULT 1');
    ensure_column_exists($pdo, 'professionals', 'accepts_waitlist', 'INTEGER NOT NULL DEFAULT 1');
    ensure_column_exists($pdo, 'professionals', 'notification_email', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'professionals', 'notification_whatsapp', 'TEXT NOT NULL DEFAULT ""');

    if (table_exists($pdo, 'peluqueros') && table_exists($pdo, 'professionals')) {
        $legacyCount = (int) $pdo->query('SELECT COUNT(*) FROM peluqueros')->fetchColumn();
        $newCount = (int) $pdo->query('SELECT COUNT(*) FROM professionals')->fetchColumn();

        if ($legacyCount > 0 && $newCount === 0) {
            $pdo->exec(
                'INSERT INTO professionals
                 (id_professional, user_professional, pass_professional, name_professional, email_professional, phone_professional, bio_professional, calendar_color, id_disciplina, activo, id_estado)
                 SELECT id_peluquero, user_peluquero, pass_peluquero, nombre_peluquero, email_peluquero, telefono_peluquero, bio_peluquero, color_peluquero, id_disciplina, activo, id_estado
                 FROM peluqueros'
            );
        }
    }

    ensure_column_exists($pdo, 'clientes', 'notas_cliente', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'servicios', 'id_disciplina', 'INTEGER');
    ensure_column_exists($pdo, 'servicios', 'duracion_minutos', 'INTEGER NOT NULL DEFAULT 60');
    ensure_column_exists($pdo, 'servicios', 'modalidad_servicio', 'TEXT NOT NULL DEFAULT "Presencial"');
    ensure_column_exists($pdo, 'servicios', 'activo', 'INTEGER NOT NULL DEFAULT 1');
    ensure_column_exists($pdo, 'servicios', 'buffer_before_min', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column_exists($pdo, 'servicios', 'buffer_after_min', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column_exists($pdo, 'servicios', 'allows_parallel', 'INTEGER NOT NULL DEFAULT 0');

    rename_column_if_exists($pdo, 'eventos', 'id_peluquero', 'id_professional');
    ensure_column_exists($pdo, 'eventos', 'estado_reserva', 'TEXT NOT NULL DEFAULT "confirmada"');
    ensure_column_exists($pdo, 'eventos', 'notas_reserva', 'TEXT NOT NULL DEFAULT ""');
    ensure_column_exists($pdo, 'eventos', 'created_at', 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP');

    $pdo->exec("UPDATE eventos SET end = datetime(start, '+60 minutes') WHERE end IS NULL OR end = ''");
}

function ensure_column_exists(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!table_exists($pdo, $table)) {
        return;
    }

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

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape_html(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('No se pudo validar la sesion del formulario');
    }
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

function request_post_int(string $key, bool $required = true): int
{
    $value = request_post_string($key, $required);
    if ($value === '' && !$required) {
        return 0;
    }
    if (!preg_match('/^-?\d+$/', $value)) {
        throw new InvalidArgumentException('Valor numerico invalido: ' . $key);
    }
    return (int) $value;
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

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
        if ($key === '') {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
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

function validate_time_value(string $time, bool $allowBlank = false): string
{
    $time = trim($time);
    if ($allowBlank && $time === '') {
        return '';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new InvalidArgumentException('Hora invalida');
    }
    return $time;
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
        flash_message('info', $message);
    }
    header('Location: ' . $path);
    exit;
}

function frontend_base_url(): string
{
    return rtrim((string) (getenv('FRONTEND_URL') ?: 'http://127.0.0.1:5173'), '/');
}

function redirect_to_frontend(string $path): void
{
    if (($_GET['legacy'] ?? '') === '1' || (string) getenv('FRONTEND_REPLACES_PHP') !== '1') {
        return;
    }

    header('Location: ' . frontend_base_url() . '/' . ltrim($path, '/'));
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

function flash_message(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = array();
    }
    $_SESSION['flash_messages'][] = array(
        'type' => $type,
        'message' => $message,
    );
}

function consume_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? array();
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : array();
}

function handle_app_exception(Throwable $exception, string $redirect): void
{
    flash_message('error', $exception->getMessage());
    header('Location: ' . $redirect);
    exit;
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

    $imageInfo = @getimagesize((string) $file['tmp_name']);
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

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
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

function save_brand_asset(array $file, string $prefix, array $allowedExtensions, array $allowedMimeTypes, ?string $currentAsset = null): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentAsset ?? '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No se pudo subir el archivo de marca');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new InvalidArgumentException('Formato de archivo no permitido');
    }

    $mime = mime_content_type((string) $file['tmp_name']);
    if ($mime === false || !in_array($mime, $allowedMimeTypes, true)) {
        throw new InvalidArgumentException('Tipo de archivo no valido');
    }

    $brandingDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'branding';
    if (!is_dir($brandingDirectory) && !mkdir($brandingDirectory, 0777, true) && !is_dir($brandingDirectory)) {
        throw new RuntimeException('No se pudo preparar la carpeta de branding');
    }

    $safeName = $prefix . '_' . uniqid('', true) . '.' . $extension;
    $destination = $brandingDirectory . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        throw new RuntimeException('No se pudo guardar el archivo de branding');
    }

    if ($currentAsset !== null && $currentAsset !== '' && $currentAsset !== $safeName) {
        $currentPath = $brandingDirectory . DIRECTORY_SEPARATOR . basename($currentAsset);
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

function ensure_professional_schedule_rows(PDO $pdo, int $professionalId): void
{
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO professional_availability
         (id_professional, weekday, is_working, start_time, end_time, break_start, break_end, slot_interval)
         VALUES (:id_professional, :weekday, :is_working, :start_time, :end_time, :break_start, :break_end, :slot_interval)'
    );

    foreach (range(0, 6) as $weekday) {
        $stmt->execute(
            array(
                ':id_professional' => $professionalId,
                ':weekday' => $weekday,
                ':is_working' => !in_array($weekday, array(0), true) ? 1 : 0,
                ':start_time' => '09:00',
                ':end_time' => '18:00',
                ':break_start' => '13:00',
                ':break_end' => '14:00',
                ':slot_interval' => 30,
            )
        );
    }
}

function ensure_professional_schedules_seeded(PDO $pdo): void
{
    $rows = fetch_all($pdo->prepare('SELECT id_professional FROM professionals'));
    foreach ($rows as $row) {
        ensure_professional_schedule_rows($pdo, (int) $row['id_professional']);
    }
}

function ensure_professional_assignments_seeded(PDO $pdo): void
{
    $pdo->exec(
        'INSERT OR IGNORE INTO professional_disciplines (id_professional, id_disciplina)
         SELECT id_professional, id_disciplina
         FROM professionals
         WHERE id_disciplina IS NOT NULL'
    );

    $pdo->exec(
        'INSERT OR IGNORE INTO professional_services (id_professional, id_servicio)
         SELECT pd.id_professional, s.id_servicio
         FROM professional_disciplines pd
         JOIN servicios s ON s.id_disciplina = pd.id_disciplina
         WHERE s.activo = 1'
    );
}

function professional_service_ids(PDO $pdo, int $professionalId): array
{
    $rows = fetch_all(
        $pdo->prepare('SELECT id_servicio FROM professional_services WHERE id_professional = :id_professional'),
        array(':id_professional' => $professionalId)
    );

    return array_map(static fn (array $row): int => (int) $row['id_servicio'], $rows);
}

function professional_can_perform_service(PDO $pdo, int $professionalId, int $serviceId): bool
{
    ensure_professional_assignments_seeded($pdo);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM professional_services ps
         JOIN servicios s ON s.id_servicio = ps.id_servicio
         WHERE ps.id_professional = :id_professional
           AND ps.id_servicio = :id_servicio
           AND s.activo = 1'
    );
    $stmt->execute(array(':id_professional' => $professionalId, ':id_servicio' => $serviceId));

    return (int) $stmt->fetchColumn() > 0;
}

function professional_weekly_schedule(PDO $pdo, int $professionalId): array
{
    ensure_professional_schedule_rows($pdo, $professionalId);
    $rows = fetch_all(
        $pdo->prepare('SELECT * FROM professional_availability WHERE id_professional = :id_professional ORDER BY weekday ASC'),
        array(':id_professional' => $professionalId)
    );

    $indexed = array();
    foreach ($rows as $row) {
        $indexed[(int) $row['weekday']] = $row;
    }

    return $indexed;
}

function professional_schedule_summary(PDO $pdo, int $professionalId): string
{
    $days = array('Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab');
    $rows = professional_weekly_schedule($pdo, $professionalId);
    $parts = array();
    foreach ($rows as $weekday => $row) {
        if ((int) $row['is_working'] !== 1) {
            continue;
        }
        $parts[] = $days[$weekday] . ' ' . $row['start_time'] . '-' . $row['end_time'];
    }
    return $parts === array() ? 'Sin horario definido' : implode(' · ', $parts);
}

function current_professional_window(PDO $pdo, int $professionalId, DateTimeImmutable $startDate): ?array
{
    $date = $startDate->format('Y-m-d');
    $exception = fetch_one(
        $pdo->prepare('SELECT * FROM professional_exceptions WHERE id_professional = :id_professional AND exception_date = :exception_date'),
        array(':id_professional' => $professionalId, ':exception_date' => $date)
    );

    if ($exception !== null) {
        if ((int) $exception['is_day_off'] === 1) {
            return null;
        }
        if ($exception['start_time'] !== '' && $exception['end_time'] !== '') {
            return array(
                'start_time' => $exception['start_time'],
                'end_time' => $exception['end_time'],
                'break_start' => '',
                'break_end' => '',
                'slot_interval' => (int) setting_value('slot_interval', '30'),
            );
        }
    }

    $rows = professional_weekly_schedule($pdo, $professionalId);
    $weekday = (int) $startDate->format('w');
    $row = $rows[$weekday] ?? null;
    if ($row === null || (int) $row['is_working'] !== 1) {
        return null;
    }
    return $row;
}

function professional_availability_background_events(PDO $pdo, int $professionalId, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
{
    $events = array();
    $hours = business_hours();
    $opening = $hours['opening'];
    $closing = $hours['closing'];
    $cursor = $rangeStart->setTime(0, 0);
    $endDate = $rangeEnd->setTime(0, 0);

    while ($cursor <= $endDate) {
        $date = $cursor->format('Y-m-d');
        $window = current_professional_window($pdo, $professionalId, $cursor);

        if ($window === null) {
            $events[] = array(
                'id' => 'availability-off-' . $date,
                'start' => $date . 'T' . $opening . ':00',
                'end' => $date . 'T' . $closing . ':00',
                'display' => 'background',
                'className' => array('availability-off'),
                'backgroundColor' => 'rgba(148, 163, 184, 0.18)',
                'extendedProps' => array('kind' => 'day_off'),
            );
            $cursor = $cursor->modify('+1 day');
            continue;
        }

        $segments = array();
        if ($opening < $window['start_time']) {
            $segments[] = array($opening, $window['start_time'], 'availability-closed');
        }
        if ($window['end_time'] < $closing) {
            $segments[] = array($window['end_time'], $closing, 'availability-closed');
        }
        if (($window['break_start'] ?? '') !== '' && ($window['break_end'] ?? '') !== '') {
            $segments[] = array($window['break_start'], $window['break_end'], 'availability-break');
        }

        foreach ($segments as $segment) {
            if ($segment[0] >= $segment[1]) {
                continue;
            }
            $events[] = array(
                'id' => $segment[2] . '-' . $date . '-' . $segment[0],
                'start' => $date . 'T' . $segment[0] . ':00',
                'end' => $date . 'T' . $segment[1] . ':00',
                'display' => 'background',
                'className' => array($segment[2]),
                'backgroundColor' => $segment[2] === 'availability-break' ? 'rgba(249, 115, 22, 0.18)' : 'rgba(148, 163, 184, 0.18)',
                'extendedProps' => array(
                    'kind' => $segment[2] === 'availability-break' ? 'break' : 'closed',
                ),
            );
        }

        $cursor = $cursor->modify('+1 day');
    }

    return $events;
}

function service_duration_minutes(PDO $pdo, int $serviceId): int
{
    $policy = service_booking_policy($pdo, $serviceId);
    return $policy['duracion_minutos'];
}

function calculate_event_end(string $start, int $durationMinutes): string
{
    return (new DateTimeImmutable($start))->modify('+' . $durationMinutes . ' minutes')->format('Y-m-d H:i:s');
}

function ensure_slot_available(PDO $pdo, int $professionalId, string $start, string $end, ?int $excludeEventId = null, ?int $serviceId = null): void
{
    $professional = fetch_one(
        $pdo->prepare('SELECT activo, booking_capacity, accepts_waitlist, id_disciplina FROM professionals WHERE id_professional = :id_professional'),
        array(':id_professional' => $professionalId)
    );
    if ($professional === null || (int) $professional['activo'] !== 1) {
        throw new InvalidArgumentException('El profesional no esta disponible');
    }

    if ($serviceId !== null && $serviceId > 0) {
        $service = fetch_one(
            $pdo->prepare('SELECT id_servicio, id_disciplina, nombre_servicio FROM servicios WHERE id_servicio = :id_servicio AND activo = 1'),
            array(':id_servicio' => $serviceId)
        );
        if ($service === null) {
            throw new InvalidArgumentException('El servicio no esta disponible');
        }
        if (!professional_can_perform_service($pdo, $professionalId, (int) $service['id_servicio'])) {
            throw new InvalidArgumentException('El profesional seleccionado no realiza este servicio');
        }
    }

    $startDate = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    $window = current_professional_window($pdo, $professionalId, $startDate);
    $holiday = global_holiday_for_date($pdo, $startDate->format('Y-m-d'));

    if ($window === null) {
        throw new InvalidArgumentException('El profesional no atiende en esa fecha');
    }

    if ($holiday !== null) {
        if ((int) ($holiday['is_closed'] ?? 1) === 1) {
            throw new InvalidArgumentException('La fecha corresponde a un feriado o cierre global');
        }

        $holidayStart = trim((string) ($holiday['start_time'] ?? ''));
        $holidayEnd = trim((string) ($holiday['end_time'] ?? ''));
        if ($holidayStart !== '' && $holidayEnd !== '') {
            $slotStart = $startDate->format('H:i');
            $slotEnd = $endDate->format('H:i');
            if ($slotStart < $holidayEnd && $slotEnd > $holidayStart) {
                throw new InvalidArgumentException('La reserva cruza un bloqueo global del calendario');
            }
        }
    }

    $slotInterval = max(5, (int) ($window['slot_interval'] ?? setting_value('slot_interval', '30')));
    if (((int) $startDate->format('i')) % $slotInterval !== 0) {
        throw new InvalidArgumentException('La hora no respeta el intervalo del profesional');
    }

    $startTime = $startDate->format('H:i');
    $endTime = $endDate->format('H:i');
    if ($startTime < $window['start_time'] || $endTime > $window['end_time']) {
        throw new InvalidArgumentException('La reserva queda fuera del horario del profesional');
    }

    if (($window['break_start'] ?? '') !== '' && ($window['break_end'] ?? '') !== '') {
        if ($startTime < $window['break_end'] && $endTime > $window['break_start']) {
            throw new InvalidArgumentException('La reserva cruza una pausa del profesional');
        }
    }

    $globalBuffer = max(0, (int) setting_value('global_buffer_min', '0'));
    $targetStart = $startDate;
    $targetEnd = $endDate;
    if ($serviceId !== null && $serviceId > 0) {
        $policy = service_booking_policy($pdo, $serviceId);
        $targetStart = $targetStart->modify('-' . ($globalBuffer + $policy['buffer_before_min']) . ' minutes');
        $targetEnd = $targetEnd->modify('+' . ($globalBuffer + $policy['buffer_after_min']) . ' minutes');
    } elseif ($globalBuffer > 0) {
        $targetStart = $targetStart->modify('-' . $globalBuffer . ' minutes');
        $targetEnd = $targetEnd->modify('+' . $globalBuffer . ' minutes');
    }

    $overlaps = overlapping_bookings($pdo, $professionalId, $targetStart->format('Y-m-d H:i:s'), $targetEnd->format('Y-m-d H:i:s'), $excludeEventId);
    if ($serviceId === null || $serviceId <= 0) {
        if ($overlaps !== array()) {
            throw new InvalidArgumentException('Ese profesional ya tiene una reserva en ese rango horario');
        }
        return;
    }

    if (!can_book_parallel($pdo, $professionalId, $serviceId, $overlaps)) {
        throw new InvalidArgumentException('No hay capacidad disponible para ese horario con este profesional');
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

function weekday_labels(): array
{
    return array(
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
    );
}

function create_password_reset_token(PDO $pdo, string $accountType, int $accountId): string
{
    $token = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

    $pdo->prepare('DELETE FROM password_reset_tokens WHERE account_type = :account_type AND account_id = :account_id AND used_at IS NULL')
        ->execute(array(':account_type' => $accountType, ':account_id' => $accountId));

    $pdo->prepare(
        'INSERT INTO password_reset_tokens (account_type, account_id, token_hash, expires_at)
         VALUES (:account_type, :account_id, :token_hash, :expires_at)'
    )->execute(
        array(
            ':account_type' => $accountType,
            ':account_id' => $accountId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        )
    );

    return $token;
}

function password_reset_record(PDO $pdo, string $token): ?array
{
    $record = fetch_one(
        $pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL'),
        array(':token_hash' => hash('sha256', $token))
    );
    if ($record === null) {
        return null;
    }
    if (new DateTimeImmutable((string) $record['expires_at']) < new DateTimeImmutable()) {
        return null;
    }
    return $record;
}

function consume_password_reset_token(PDO $pdo, int $tokenId): void
{
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id_token = :id_token')
        ->execute(array(':id_token' => $tokenId));
}

function seed_demo_content(PDO $pdo): void
{
    $professionalCount = (int) $pdo->query('SELECT COUNT(*) FROM professionals')->fetchColumn();
    $serviceCount = (int) $pdo->query('SELECT COUNT(*) FROM servicios')->fetchColumn();
    $clientCount = (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();

    if ($professionalCount > 0 || $serviceCount > 0 || $clientCount > 0) {
        return;
    }

    $disciplineMap = array();
    foreach (fetch_all($pdo->prepare('SELECT id_disciplina, nombre_disciplina FROM disciplinas')) as $disciplina) {
        $disciplineMap[$disciplina['nombre_disciplina']] = (int) $disciplina['id_disciplina'];
    }

    $servicesDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'services';
    $fallbackImage = is_file($servicesDirectory . DIRECTORY_SEPARATOR . 'cortedepelo.jpg') ? 'cortedepelo.jpg' : '2.jpg';

    $professionalStmt = $pdo->prepare(
        'INSERT INTO professionals
         (user_professional, pass_professional, name_professional, email_professional, phone_professional, bio_professional, calendar_color, id_disciplina, activo, id_estado)
         VALUES (:user_professional, :pass_professional, :name_professional, :email_professional, :phone_professional, :bio_professional, :calendar_color, :id_disciplina, 1, 2)'
    );
    $professionals = array(
        array('ana.bustos', 'Ana Bustos', 'ana@sistema.local', '+56911111111', 'Especialista en evaluaciones y sesiones personalizadas.', '#0f766e', $disciplineMap['Bienestar'] ?? null),
        array('matias.reyes', 'Matias Reyes', 'matias@sistema.local', '+56922222222', 'Profesional orientado a asesoria y seguimiento.', '#2563eb', $disciplineMap['Asesoria'] ?? null),
        array('laura.mendez', 'Laura Mendez', 'laura@sistema.local', '+56977777777', 'Acompana procesos de atencion con foco en continuidad y experiencia del cliente.', '#7c3aed', $disciplineMap['Estetica'] ?? null),
    );
    foreach ($professionals as $professional) {
        $professionalStmt->execute(
            array(
                ':user_professional' => $professional[0],
                ':pass_professional' => hash_password_value('Profesional123'),
                ':name_professional' => $professional[1],
                ':email_professional' => $professional[2],
                ':phone_professional' => $professional[3],
                ':bio_professional' => $professional[4],
                ':calendar_color' => $professional[5],
                ':id_disciplina' => $professional[6],
            )
        );
    }

    $serviceStmt = $pdo->prepare(
        'INSERT INTO servicios
         (id_disciplina, nombre_servicio, descripcion_servicio, precio_servicio, duracion_minutos, modalidad_servicio, img_servicio, color, textColor, activo)
         VALUES (:id_disciplina, :nombre_servicio, :descripcion_servicio, :precio_servicio, :duracion_minutos, :modalidad_servicio, :img_servicio, :color, :textColor, 1)'
    );
    $services = array(
        array($disciplineMap['Bienestar'] ?? null, 'Sesion de evaluacion', 'Primer encuentro para detectar necesidades y objetivos.', 28000, 60, 'Presencial', $fallbackImage, '#0f766e', '#ffffff'),
        array($disciplineMap['Asesoria'] ?? null, 'Asesoria personalizada', 'Bloque de trabajo con seguimiento y definicion de plan.', 36000, 90, 'Presencial', $fallbackImage, '#1d4ed8', '#ffffff'),
        array($disciplineMap['Estetica'] ?? null, 'Sesion express', 'Atencion breve para clientes que necesitan una gestion rapida del servicio.', 18000, 30, 'Presencial', $fallbackImage, '#7c3aed', '#ffffff'),
    );
    foreach ($services as $service) {
        $serviceStmt->execute(
            array(
                ':id_disciplina' => $service[0],
                ':nombre_servicio' => $service[1],
                ':descripcion_servicio' => $service[2],
                ':precio_servicio' => $service[3],
                ':duracion_minutos' => $service[4],
                ':modalidad_servicio' => $service[5],
                ':img_servicio' => $service[6],
                ':color' => $service[7],
                ':textColor' => $service[8],
            )
        );
    }

    $clientStmt = $pdo->prepare(
        'INSERT INTO clientes (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, notas_cliente, id_estado)
         VALUES (:nombre, :apellido, :telefono, :correo, :usuario, :password, :notas, 3)'
    );
    $clients = array(
        array('Camila', 'Torres', '+56933333333', 'camila@sistema.local', 'camila.torres', 'Cliente demo interesada en seguimiento mensual.'),
        array('Diego', 'Molina', '+56944444444', 'diego@sistema.local', 'diego.molina', 'Cliente demo para bloques de asesoria.'),
        array('Valentina', 'Rojas', '+56955555555', 'valentina@sistema.local', 'valentina.rojas', 'Cliente demo con preferencia por atenciones de corta duracion.'),
    );
    foreach ($clients as $client) {
        $clientStmt->execute(
            array(
                ':nombre' => $client[0],
                ':apellido' => $client[1],
                ':telefono' => $client[2],
                ':correo' => $client[3],
                ':usuario' => $client[4],
                ':password' => hash_password_value('Cliente123'),
                ':notas' => $client[5],
            )
        );
    }

    $professionalIds = $pdo->query('SELECT id_professional FROM professionals ORDER BY id_professional ASC')->fetchAll(PDO::FETCH_COLUMN) ?: array();
    $serviceIds = $pdo->query('SELECT id_servicio FROM servicios ORDER BY id_servicio ASC')->fetchAll(PDO::FETCH_COLUMN) ?: array();
    $clientIds = $pdo->query('SELECT id_cliente FROM clientes ORDER BY id_cliente ASC')->fetchAll(PDO::FETCH_COLUMN) ?: array();

    if ($professionalIds !== array() && $serviceIds !== array() && $clientIds !== array()) {
        foreach ($professionalIds as $professionalId) {
            ensure_professional_schedule_rows($pdo, (int) $professionalId);
        }

        $demoEvents = array(
            array('tomorrow 10:00', (int) $professionalIds[0], (int) $clientIds[0], (int) $serviceIds[0], 'confirmada', 'Reserva demo para mostrar un bloque confirmado.'),
            array('tomorrow 15:00', (int) ($professionalIds[1] ?? $professionalIds[0]), (int) ($clientIds[1] ?? $clientIds[0]), (int) ($serviceIds[1] ?? $serviceIds[0]), 'pendiente', 'Reserva demo pendiente para pruebas de filtros.'),
            array('tomorrow +2 days 11:30', (int) ($professionalIds[2] ?? $professionalIds[0]), (int) ($clientIds[2] ?? $clientIds[0]), (int) ($serviceIds[2] ?? $serviceIds[0]), 'en_progreso', 'Reserva demo corta para mostrar reprogramacion.'),
            array('tomorrow +3 days 09:30', (int) ($professionalIds[0] ?? $professionalIds[0]), (int) ($clientIds[1] ?? $clientIds[0]), (int) ($serviceIds[0] ?? $serviceIds[0]), 'completada', 'Reserva demo historica para metricas.'),
        );

        $eventStmt = $pdo->prepare(
            'INSERT INTO eventos (title, id_professional, id_cliente, id_servicio, start, end, estado_reserva, notas_reserva)
             VALUES (:title, :id_professional, :id_cliente, :id_servicio, :start, :end, :estado_reserva, :notas_reserva)'
        );

        foreach ($demoEvents as $demoEvent) {
            $start = (new DateTimeImmutable($demoEvent[0]))->format('Y-m-d H:i:s');
            $end = calculate_event_end($start, service_duration_minutes($pdo, (int) $demoEvent[3]));
            $eventStmt->execute(
                array(
                    ':title' => 'Reservado',
                    ':id_professional' => $demoEvent[1],
                    ':id_cliente' => $demoEvent[2],
                    ':id_servicio' => $demoEvent[3],
                    ':start' => $start,
                    ':end' => $end,
                    ':estado_reserva' => $demoEvent[4],
                    ':notas_reserva' => $demoEvent[5],
                )
            );
        }

        $pdo->prepare(
            'INSERT OR REPLACE INTO professional_exceptions (id_professional, exception_date, is_day_off, start_time, end_time, notes)
             VALUES (:id_professional, :exception_date, 0, :start_time, :end_time, :notes)'
        )->execute(
            array(
                ':id_professional' => (int) $professionalIds[0],
                ':exception_date' => (new DateTimeImmutable('tomorrow +1 day'))->format('Y-m-d'),
                ':start_time' => '11:00',
                ':end_time' => '17:00',
                ':notes' => 'Horario especial demo',
            )
        );

        $pdo->prepare(
            'INSERT OR IGNORE INTO global_holidays (holiday_date, holiday_name, is_closed, start_time, end_time, notes)
             VALUES (:holiday_date, :holiday_name, :is_closed, :start_time, :end_time, :notes)'
        )->execute(
            array(
                ':holiday_date' => (new DateTimeImmutable('tomorrow +5 days'))->format('Y-m-d'),
                ':holiday_name' => 'Bloqueo operativo demo',
                ':is_closed' => 0,
                ':start_time' => '12:00',
                ':end_time' => '15:00',
                ':notes' => 'Bloque de mantenimiento para mostrar feriados parciales.',
            )
        );
    }
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
