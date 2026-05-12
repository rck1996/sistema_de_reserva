<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_professional', '2', '../staff-login.php');

$pdo = app_pdo();
require_csrf();

try {
    $id = request_session_int('id_professional');
    $usuario = request_post_string('user_professional');
    $nombre = request_post_string('name_professional');
    $newPassword = request_post_string('pass_professional', false);

    ensure_unique_value($pdo, 'professionals', 'user_professional', $usuario, $id, 'id_professional');

    $current = fetch_one($pdo->prepare('SELECT pass_professional FROM professionals WHERE id_professional = :id'), array(':id' => $id));
    if ($current === null) {
        throw new InvalidArgumentException('Profesional no encontrado');
    }

    $passwordToStore = $newPassword === '' ? $current['pass_professional'] : hash_password_value($newPassword);

    $stmt = $pdo->prepare('UPDATE professionals SET user_professional = :usuario, pass_professional = :password, name_professional = :nombre WHERE id_professional = :id');
    $stmt->execute(array(':usuario' => $usuario, ':password' => $passwordToStore, ':nombre' => $nombre, ':id' => $id));

    $_SESSION['name_professional'] = $nombre;

    app_redirect('../staff-dashboard.php', 'Tus datos fueron actualizados');
} catch (Throwable $exception) {
    handle_app_exception($exception, '../staff-dashboard.php');
}
