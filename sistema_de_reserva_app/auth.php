<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = app_pdo();
$action = request_post_string('action');

function login_user(PDO $pdo, string $table, string $userColumn, string $passwordColumn, string $lookupValue, string $password, array $sessionMap, string $redirectSuccess, string $redirectFailure, ?string $roleFilter = null): void
{
    $sql = "SELECT * FROM {$table} WHERE {$userColumn} = :user";
    $params = array(':user' => $lookupValue);

    if ($roleFilter !== null) {
        $sql .= ' AND id_estado = :id_estado';
        $params[':id_estado'] = $roleFilter;
    }

    $stmt = $pdo->prepare($sql);
    $user = fetch_one($stmt, $params);

    if ($user === null || !password_matches($password, (string) $user[$passwordColumn])) {
        app_redirect($redirectFailure, 'Credenciales inválidas');
    }

    if (password_needs_upgrade((string) $user[$passwordColumn])) {
        $upgrade = $pdo->prepare("UPDATE {$table} SET {$passwordColumn} = :password WHERE {$sessionMap['pk']} = :id");
        $upgrade->execute(array(':password' => hash_password_value($password), ':id' => $user[$sessionMap['pk']]));
    }

    session_regenerate_id(true);

    foreach ($sessionMap['session'] as $sessionKey => $column) {
        $_SESSION[$sessionKey] = $user[$column];
    }

    app_redirect($redirectSuccess, $sessionMap['message']);
}

try {
    switch ($action) {
        case 'login-admin':
            login_user(
                $pdo,
                'admin',
                'user_admin',
                'pass_admin',
                validate_email_address(request_post_string('email')),
                request_post_string('password'),
                array(
                    'pk' => 'id_admin',
                    'session' => array('id_admin' => 'id_admin', 'id_estado' => 'id_estado'),
                    'message' => 'Bienvenido al centro de administración',
                ),
                'admin-dashboard.php',
                'admin-login.php',
                '1'
            );
            break;

        case 'login-staff':
            login_user(
                $pdo,
                'professionals',
                'user_professional',
                'pass_professional',
                request_post_string('username'),
                request_post_string('password'),
                array(
                    'pk' => 'id_professional',
                    'session' => array(
                        'id_professional' => 'id_professional',
                        'name_professional' => 'name_professional',
                        'id_estado' => 'id_estado',
                    ),
                    'message' => 'Bienvenido al panel profesional',
                ),
                'staff-dashboard.php',
                'staff-login.php',
                '2'
            );
            break;

        case 'login-customer':
            login_user(
                $pdo,
                'clientes',
                'user_cliente',
                'pass_cliente',
                request_post_string('username'),
                request_post_string('password'),
                array(
                    'pk' => 'id_cliente',
                    'session' => array(
                        'id_cliente' => 'id_cliente',
                        'nombre_cliente' => 'nombre_cliente',
                        'apellido_cliente' => 'apellido_cliente',
                        'id_estado' => 'id_estado',
                    ),
                    'message' => 'Bienvenido a tu panel',
                ),
                'customer-dashboard.php',
                'index.php#acceso'
            );
            break;

        case 'register-customer':
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

            $stmt = $pdo->prepare(
                'INSERT INTO clientes (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, id_estado)
                 VALUES (:nombre, :apellido, :telefono, :correo, :usuario, :password, 3)'
            );
            $stmt->execute(
                array(
                    ':nombre' => $firstName,
                    ':apellido' => $lastName,
                    ':telefono' => $phone,
                    ':correo' => $email,
                    ':usuario' => $username,
                    ':password' => $passwordHash,
                )
            );

            app_redirect('index.php#acceso', 'Cuenta creada. Tu usuario es: ' . $username);
            break;

        default:
            app_redirect('index.php');
    }
} catch (Throwable $exception) {
    $fallback = $action === 'login-admin' ? 'admin-login.php' : ($action === 'login-staff' ? 'staff-login.php' : 'index.php#acceso');
    handle_app_exception($exception, $fallback);
}
