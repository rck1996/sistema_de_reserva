<?php

declare(strict_types=1);

require_once __DIR__ . '/migrator.php';

$pdo = backend_pdo();
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'migrations';

foreach (migration_status($pdo, $directory) as $row) {
    echo ($row['applied'] ? '[x] ' : '[ ] ') . $row['migration'] . PHP_EOL;
}
