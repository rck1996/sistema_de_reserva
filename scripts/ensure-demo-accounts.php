<?php

declare(strict_types=1);

$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/sistema_de_reserva_app/data/sistema_de_reserva.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->prepare('UPDATE professionals SET pass_professional = :password, id_estado = 2, activo = 1 WHERE user_professional = :user')
    ->execute(array(
        ':password' => password_hash('Profesional123', PASSWORD_DEFAULT),
        ':user' => 'pro1',
    ));

$existing = $pdo->prepare('SELECT id_cliente FROM clientes WHERE user_cliente = :user');
$existing->execute(array(':user' => 'cliente_demo'));

if ($existing->fetchColumn() === false) {
    $pdo->prepare(
        'INSERT INTO clientes (nombre_cliente, apellido_cliente, telefono_cliente, correo_cliente, user_cliente, pass_cliente, notas_cliente, id_estado)
         VALUES (:nombre, :apellido, :telefono, :correo, :usuario, :password, :notas, 3)'
    )->execute(array(
        ':nombre' => 'Cliente',
        ':apellido' => 'Demo',
        ':telefono' => '+56912345678',
        ':correo' => 'cliente.demo@sistema.local',
        ':usuario' => 'cliente_demo',
        ':password' => password_hash('Cliente123', PASSWORD_DEFAULT),
        ':notas' => 'Cuenta demo estable para pruebas de cliente.',
    ));
} else {
    $pdo->prepare('UPDATE clientes SET pass_cliente = :password, id_estado = 3 WHERE user_cliente = :user')
        ->execute(array(
            ':password' => password_hash('Cliente123', PASSWORD_DEFAULT),
            ':user' => 'cliente_demo',
        ));
}

echo "Demo accounts ready\n";
