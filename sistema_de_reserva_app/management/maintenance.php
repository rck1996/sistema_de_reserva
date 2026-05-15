<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');
require_csrf();

$pdo = app_pdo();
$accion = trim((string) ($_GET['accion'] ?? ''));

try {
    if ($accion === 'backup') {
        $backupName = create_sqlite_backup();
        audit_log($pdo, 'backup', 0, 'created', 'Backup SQLite generado', array('file' => $backupName));
        app_redirect('../admin-settings.php#operacion', 'Backup creado: ' . $backupName);
    }

    if ($accion === 'restore') {
        $backupName = request_post_string('backup_name');
        restore_sqlite_backup($backupName);
        audit_log($pdo, 'backup', 0, 'restored', 'Backup SQLite restaurado', array('file' => $backupName));
        app_redirect('../admin-settings.php#operacion', 'Backup restaurado: ' . $backupName);
    }

    if ($accion === 'dispatch-notifications') {
        $processed = dispatch_notification_queue($pdo);
        audit_log($pdo, 'notification', 0, 'dispatched', 'Cola de notificaciones procesada', $processed);
        app_redirect('../admin-settings.php#notificaciones', 'Notificaciones procesadas');
    }

    app_redirect('../admin-settings.php#operacion');
} catch (Throwable $exception) {
    handle_app_exception($exception, '../admin-settings.php#operacion');
}
