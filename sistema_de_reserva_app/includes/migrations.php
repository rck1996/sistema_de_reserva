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
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_token_hash ON password_reset_tokens (token_hash)');
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
