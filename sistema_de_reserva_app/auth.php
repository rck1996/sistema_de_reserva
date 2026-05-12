<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = app_pdo();
$action = request_post_string('action');

function login_user(
    PDO $pdo,
    string $table,
    string $userColumn,
    string $passwordColumn,
    string $lookupValue,
    string $password,
    array $sessionMap,
    string $redirectSuccess,
    string $redirectFailure,
    ?string $roleFilter = null
): void {
    $sql = "SELECT * FROM {$table} WHERE {$userColumn} = :user";
    $params = array(':user' => $lookupValue);

    if ($roleFilter !== null) {
        $sql .= ' AND id_estado = :id_estado';
        $params[':id_estado'] = $roleFilter;
    }

    $user = fetch_one($pdo->prepare($sql), $params);

    if ($user === null || !password_matches($password, (string) $user[$passwordColumn])) {
        app_redirect($redirectFailure, 'Credenciales invalidas');
    }

    if (password_needs_upgrade((string) $user[$passwordColumn])) {
        $pdo->prepare("UPDATE {$table} SET {$passwordColumn} = :password WHERE {$sessionMap['pk']} = :id")
            ->execute(array(':password' => hash_password_value($password), ':id' => $user[$sessionMap['pk']]));
    }

    session_regenerate_id(true);
    $_SESSION['last_activity_at'] = time();

    foreach ($sessionMap['session'] as $sessionKey => $column) {
        $_SESSION[$sessionKey] = $user[$column];
    }

    app_redirect($redirectSuccess, $sessionMap['message']);
}

function lookup_account_for_reset(PDO $pdo, string $identity): ?array
{
    $identity = trim($identity);
    if ($identity === '') {
        throw new InvalidArgumentException('Debes indicar correo o usuario');
    }

    $checks = array(
        array('table' => 'admin', 'type' => 'admin', 'id_column' => 'id_admin', 'where' => 'user_admin = :identity'),
        array('table' => 'professionals', 'type' => 'professional', 'id_column' => 'id_professional', 'where' => '(email_professional = :identity OR user_professional = :identity)'),
        array('table' => 'clientes', 'type' => 'customer', 'id_column' => 'id_cliente', 'where' => '(correo_cliente = :identity OR user_cliente = :identity)'),
    );

    foreach ($checks as $check) {
        $account = fetch_one(
            $pdo->prepare('SELECT * FROM ' . $check['table'] . ' WHERE ' . $check['where'] . ' LIMIT 1'),
            array(':identity' => $identity)
        );
        if ($account !== null) {
            return array(
                'account_type' => $check['type'],
                'account_id' => (int) $account[$check['id_column']],
            );
        }
    }

    return null;
}

function update_account_password(PDO $pdo, string $accountType, int $accountId, string $passwordHash): void
{
    if ($accountType === 'admin') {
        $pdo->prepare('UPDATE admin SET pass_admin = :password WHERE id_admin = :id')->execute(array(':password' => $passwordHash, ':id' => $accountId));
        return;
    }
    if ($accountType === 'professional') {
        $pdo->prepare('UPDATE professionals SET pass_professional = :password WHERE id_professional = :id')->execute(array(':password' => $passwordHash, ':id' => $accountId));
        return;
    }
    if ($accountType === 'customer') {
        $pdo->prepare('UPDATE clientes SET pass_cliente = :password WHERE id_cliente = :id')->execute(array(':password' => $passwordHash, ':id' => $accountId));
        return;
    }

    throw new InvalidArgumentException('Tipo de cuenta no valido');
}

try {
    require_csrf();

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
                    'message' => 'Bienvenido al centro de administracion',
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

            $pdo->prepare(
                'INSERT INTO clientes (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, id_estado)
                 VALUES (:nombre, :apellido, :telefono, :correo, :usuario, :password, 3)'
            )->execute(
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

        case 'request-password-reset':
            $account = lookup_account_for_reset($pdo, request_post_string('identity'));
            if ($account === null) {
                app_redirect('index.php#acceso', 'Si la cuenta existe, el enlace de recuperacion ya fue generado');
            }

            $token = create_password_reset_token($pdo, $account['account_type'], $account['account_id']);
            app_redirect('reset-password.php?token=' . urlencode($token), 'Recuperacion preparada. Define una nueva contrasena.');
            break;

        case 'reset-password':
            $token = request_post_string('token');
            $password = hash_password_value(request_post_string('password'));
            $record = password_reset_record($pdo, $token);
            if ($record === null) {
                throw new InvalidArgumentException('El enlace de recuperacion no es valido o expiro');
            }

            update_account_password($pdo, (string) $record['account_type'], (int) $record['account_id'], $password);
            consume_password_reset_token($pdo, (int) $record['id_token']);

            app_redirect('index.php#acceso', 'Contrasena actualizada. Ya puedes iniciar sesion.');
            break;

        default:
            app_redirect('index.php');
    }
} catch (Throwable $exception) {
    $fallback = in_array($action, array('login-admin'), true)
        ? 'admin-login.php'
        : (in_array($action, array('login-staff'), true) ? 'staff-login.php' : 'index.php#acceso');
    handle_app_exception($exception, $fallback);
}
