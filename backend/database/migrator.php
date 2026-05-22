<?php

declare(strict_types=1);

require_once __DIR__ . '/connection.php';

function ensure_migration_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(255) PRIMARY KEY,
            executed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )'
    );
}

function applied_migrations(PDO $pdo): array
{
    ensure_migration_table($pdo);
    $rows = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration ASC')->fetchAll(PDO::FETCH_COLUMN);

    return array_fill_keys(array_map('strval', $rows ?: array()), true);
}

function available_migrations(string $directory): array
{
    $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: array();
    sort($files, SORT_STRING);

    return $files;
}

function run_pending_migrations(PDO $pdo, string $directory): array
{
    ensure_migration_table($pdo);
    $applied = applied_migrations($pdo);
    $executed = array();

    foreach (available_migrations($directory) as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migracion vacia o no legible: ' . $name);
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $statement = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
            $statement->execute(array(':migration' => $name));
            $pdo->commit();
            $executed[] = $name;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw new RuntimeException('Fallo migracion ' . $name . ': ' . $exception->getMessage(), 0, $exception);
        }
    }

    return $executed;
}

function migration_status(PDO $pdo, string $directory): array
{
    $applied = applied_migrations($pdo);
    $status = array();

    foreach (available_migrations($directory) as $file) {
        $name = basename($file);
        $status[] = array(
            'migration' => $name,
            'applied' => isset($applied[$name]),
        );
    }

    return $status;
}
