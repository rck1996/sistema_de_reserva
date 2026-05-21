<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$pdo = app_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        echo json_encode(array(
            'ok' => true,
            'csrfToken' => csrf_token(),
            'session' => current_json_session(),
        ));
        exit;
    }

    require_csrf();
    $action = request_post_string('action');

    if ($action === 'logout') {
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        echo json_encode(array('ok' => true));
        exit;
    }

    if ($action === 'login-admin') {
        json_login_user($pdo, 'admin', 'user_admin', 'pass_admin', validate_email_address(request_post_string('email')), request_post_string('password'), request_post_string('remember_me', false) === '1', array(
            'id_admin' => 'id_admin',
            'id_estado' => 'id_estado',
        ), 'admin', '1');
    } elseif ($action === 'login-staff') {
        json_login_user($pdo, 'professionals', 'user_professional', 'pass_professional', request_post_string('username'), request_post_string('password'), request_post_string('remember_me', false) === '1', array(
            'id_professional' => 'id_professional',
            'name_professional' => 'name_professional',
            'id_estado' => 'id_estado',
        ), 'staff', '2');
    } elseif ($action === 'login-customer') {
        json_login_user($pdo, 'clientes', 'user_cliente', 'pass_cliente', request_post_string('username'), request_post_string('password'), request_post_string('remember_me', false) === '1', array(
            'id_cliente' => 'id_cliente',
            'nombre_cliente' => 'nombre_cliente',
            'apellido_cliente' => 'apellido_cliente',
            'id_estado' => 'id_estado',
        ), 'customer', '3');
    } elseif ($action === 'register-customer') {
        $firstName = request_post_string('first_name');
        $lastName = request_post_string('last_name');
        $phone = validate_phone(request_post_string('phone'));
        $email = validate_email_address(request_post_string('email'));
        $username = request_post_string('username', false);
        $passwordHash = hash_password_value(request_post_string('password'));

        if ($username === '') {
            $username = generate_username_from_email($email);
        }

        ensure_unique_value($pdo, 'clientes', 'correo_cliente', $email);
        $candidate = $username;
        $suffix = 1;
        while (true) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE user_cliente = :user_cliente');
            $stmt->execute(array(':user_cliente' => $candidate));
            if ((int) $stmt->fetchColumn() === 0) {
                $username = $candidate;
                break;
            }
            $candidate = $username . $suffix;
            $suffix++;
        }

        $pdo->prepare(
            'INSERT INTO clientes (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, id_estado)
             VALUES (:nombre, :apellido, :telefono, :correo, :usuario, :password, 3)'
        )->execute(array(
            ':nombre' => $firstName,
            ':apellido' => $lastName,
            ':telefono' => $phone,
            ':correo' => $email,
            ':usuario' => $username,
            ':password' => $passwordHash,
        ));

        echo json_encode(array('ok' => true, 'username' => $username, 'message' => 'Cuenta creada. Ahora puedes ingresar.'));
        exit;
    } else {
        throw new InvalidArgumentException('Accion no valida');
    }

    echo json_encode(array('ok' => true, 'csrfToken' => csrf_token(), 'session' => current_json_session()));
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => $exception->getMessage(), 'csrfToken' => csrf_token()));
}

function json_login_user(PDO $pdo, string $table, string $userColumn, string $passwordColumn, string $lookupValue, string $password, bool $remember, array $sessionMap, string $role, string $roleId): void
{
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$userColumn} = :user AND id_estado = :id_estado LIMIT 1");
    $user = fetch_one($stmt, array(':user' => $lookupValue, ':id_estado' => $roleId));

    if ($user === null || !password_matches($password, (string) $user[$passwordColumn])) {
        throw new RuntimeException('Credenciales invalidas');
    }

    if (password_needs_upgrade((string) $user[$passwordColumn])) {
        $primaryKey = array_key_first($sessionMap);
        $pdo->prepare("UPDATE {$table} SET {$passwordColumn} = :password WHERE {$primaryKey} = :id")
            ->execute(array(':password' => hash_password_value($password), ':id' => $user[$primaryKey]));
    }

    session_regenerate_id(true);
    $_SESSION['last_activity_at'] = time();
    $_SESSION['json_role'] = $role;
    $_SESSION['remember_me'] = $remember ? 1 : 0;

    foreach ($sessionMap as $sessionKey => $column) {
        $_SESSION[$sessionKey] = $user[$column];
    }

    persist_session_cookie($remember);
}

function current_json_session(): array
{
    if (!empty($_SESSION['id_admin']) && (string) ($_SESSION['id_estado'] ?? '') === '1') {
        return array('authenticated' => true, 'role' => 'admin', 'name' => 'Administracion');
    }
    if (!empty($_SESSION['id_professional']) && (string) ($_SESSION['id_estado'] ?? '') === '2') {
        return array('authenticated' => true, 'role' => 'staff', 'name' => (string) ($_SESSION['name_professional'] ?? 'Profesional'));
    }
    if (!empty($_SESSION['id_cliente']) && (string) ($_SESSION['id_estado'] ?? '') === '3') {
        return array('authenticated' => true, 'role' => 'customer', 'name' => trim((string) ($_SESSION['nombre_cliente'] ?? '') . ' ' . (string) ($_SESSION['apellido_cliente'] ?? '')));
    }

    return array('authenticated' => false, 'role' => 'guest', 'name' => '');
}
