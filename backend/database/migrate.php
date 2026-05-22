<?php

declare(strict_types=1);

require_once __DIR__ . '/migrator.php';

$pdo = backend_pdo();
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'migrations';
$executed = run_pending_migrations($pdo, $directory);

if ($executed === array()) {
    echo "No pending migrations.\n";
    exit(0);
}

foreach ($executed as $migration) {
    echo "Migrated: {$migration}\n";
}
