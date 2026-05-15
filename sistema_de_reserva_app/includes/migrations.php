<?php

declare(strict_types=1);

function initialize_schema_migrations(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
}

function registered_sqlite_migrations(): array
{
    return array(
        '2026_05_12_001_calendar_indexes' => static function (PDO $pdo): void {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_eventos_professional_start ON eventos (id_professional, start)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_eventos_cliente_start ON eventos (id_cliente, start)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_eventos_status_start ON eventos (estado_reserva, start)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_professional_availability_lookup ON professional_availability (id_professional, weekday)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_professional_exceptions_lookup ON professional_exceptions (id_professional, exception_date)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_professional_services_service ON professional_services (id_servicio, id_professional)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_professional_disciplines_discipline ON professional_disciplines (id_disciplina, id_professional)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_token_hash ON password_reset_tokens (token_hash)');
        },
        '2026_05_14_001_operational_expansion' => static function (PDO $pdo): void {
            ensure_column_exists($pdo, 'professionals', 'booking_capacity', 'INTEGER NOT NULL DEFAULT 1');
            ensure_column_exists($pdo, 'professionals', 'accepts_waitlist', 'INTEGER NOT NULL DEFAULT 1');
            ensure_column_exists($pdo, 'professionals', 'notification_email', 'TEXT NOT NULL DEFAULT ""');
            ensure_column_exists($pdo, 'professionals', 'notification_whatsapp', 'TEXT NOT NULL DEFAULT ""');

            ensure_column_exists($pdo, 'servicios', 'buffer_before_min', 'INTEGER NOT NULL DEFAULT 0');
            ensure_column_exists($pdo, 'servicios', 'buffer_after_min', 'INTEGER NOT NULL DEFAULT 0');
            ensure_column_exists($pdo, 'servicios', 'allows_parallel', 'INTEGER NOT NULL DEFAULT 0');

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS global_holidays (
                    id_holiday INTEGER PRIMARY KEY AUTOINCREMENT,
                    holiday_date TEXT NOT NULL UNIQUE,
                    holiday_name TEXT NOT NULL,
                    is_closed INTEGER NOT NULL DEFAULT 1,
                    start_time TEXT NOT NULL DEFAULT "",
                    end_time TEXT NOT NULL DEFAULT "",
                    notes TEXT NOT NULL DEFAULT ""
                )'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS waitlist_requests (
                    id_waitlist INTEGER PRIMARY KEY AUTOINCREMENT,
                    id_cliente INTEGER NOT NULL,
                    id_professional INTEGER,
                    id_servicio INTEGER NOT NULL,
                    requested_start TEXT NOT NULL,
                    requested_end TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT "pendiente",
                    notes TEXT NOT NULL DEFAULT "",
                    matched_event_id INTEGER,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE,
                    FOREIGN KEY (id_professional) REFERENCES professionals(id_professional) ON DELETE SET NULL,
                    FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE CASCADE,
                    FOREIGN KEY (matched_event_id) REFERENCES eventos(id_evento) ON DELETE SET NULL
                )'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS notification_log (
                    id_notification INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel TEXT NOT NULL,
                    recipient TEXT NOT NULL,
                    template_key TEXT NOT NULL,
                    subject TEXT NOT NULL DEFAULT "",
                    message_body TEXT NOT NULL,
                    payload_json TEXT NOT NULL DEFAULT "",
                    related_event_id INTEGER,
                    status TEXT NOT NULL DEFAULT "queued",
                    scheduled_for TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    delivered_at TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (related_event_id) REFERENCES eventos(id_evento) ON DELETE SET NULL
                )'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS audit_log (
                    id_audit INTEGER PRIMARY KEY AUTOINCREMENT,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NOT NULL DEFAULT 0,
                    entity_type TEXT NOT NULL,
                    entity_id INTEGER NOT NULL DEFAULT 0,
                    action TEXT NOT NULL,
                    summary TEXT NOT NULL,
                    payload_json TEXT NOT NULL DEFAULT "",
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );

            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_waitlist_status_start ON waitlist_requests (status, requested_start)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notifications_status_schedule ON notification_log (status, scheduled_for)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_entity_time ON audit_log (entity_type, entity_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_holidays_date ON global_holidays (holiday_date)');
        },
    );
}

function run_registered_migrations(PDO $pdo): void
{
    initialize_schema_migrations($pdo);
    $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: array();
    $appliedLookup = array_fill_keys($applied, true);

    foreach (registered_sqlite_migrations() as $version => $migration) {
        if (isset($appliedLookup[$version])) {
            continue;
        }

        $pdo->beginTransaction();
        try {
            $migration($pdo);
            $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)')
                ->execute(array(':version' => $version));
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
