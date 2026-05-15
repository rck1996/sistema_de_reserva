<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');

$pdo = app_pdo();
$type = trim((string) ($_GET['type'] ?? 'reservas'));
$format = trim((string) ($_GET['format'] ?? 'csv'));

if (!in_array($type, array('reservas', 'clientes'), true) || !in_array($format, array('csv', 'pdf'), true)) {
    throw new InvalidArgumentException('Exportacion no valida');
}

if ($type === 'reservas') {
    $rows = fetch_all(
        $pdo->prepare(
            'SELECT eventos.id_evento, eventos.start, eventos.end, eventos.estado_reserva,
                    clientes.nombre_cliente, clientes.apellido_cliente, clientes.correo_cliente,
                    professionals.name_professional, servicios.nombre_servicio, servicios.precio_servicio
             FROM eventos
             JOIN clientes ON eventos.id_cliente = clientes.id_cliente
             JOIN professionals ON eventos.id_professional = professionals.id_professional
             JOIN servicios ON eventos.id_servicio = servicios.id_servicio
             ORDER BY eventos.start ASC'
        )
    );

    $headers = array('ID', 'Inicio', 'Termino', 'Estado', 'Cliente', 'Correo', 'Profesional', 'Servicio', 'Precio');
    $payload = array_map(
        static function (array $row): array {
            return array(
                (string) $row['id_evento'],
                (string) $row['start'],
                (string) $row['end'],
                (string) $row['estado_reserva'],
                trim((string) $row['nombre_cliente'] . ' ' . (string) $row['apellido_cliente']),
                (string) $row['correo_cliente'],
                (string) $row['name_professional'],
                (string) $row['nombre_servicio'],
                (string) $row['precio_servicio'],
            );
        },
        $rows
    );
    audit_log($pdo, 'export', 0, 'generated', 'Exportacion de reservas', array('format' => $format));

    if ($format === 'csv') {
        export_rows_as_csv('reservas.csv', $headers, $payload);
    }

    $lines = array_map(static fn (array $row): string => implode(' | ', $row), $payload);
    send_pdf_download('reservas.pdf', 'Exportacion de reservas', $lines);
}

$rows = fetch_all(
    $pdo->prepare(
        'SELECT id_cliente, nombre_cliente, apellido_cliente, correo_cliente, telefono_cliente, user_cliente, notas_cliente
         FROM clientes ORDER BY apellido_cliente, nombre_cliente'
    )
);
$headers = array('ID', 'Nombre', 'Correo', 'Telefono', 'Usuario', 'Notas');
$payload = array_map(
    static function (array $row): array {
        return array(
            (string) $row['id_cliente'],
            trim((string) $row['nombre_cliente'] . ' ' . (string) $row['apellido_cliente']),
            (string) $row['correo_cliente'],
            (string) $row['telefono_cliente'],
            (string) $row['user_cliente'],
            (string) $row['notas_cliente'],
        );
    },
    $rows
);
audit_log($pdo, 'export', 0, 'generated', 'Exportacion de clientes', array('format' => $format));

if ($format === 'csv') {
    export_rows_as_csv('clientes.csv', $headers, $payload);
}

$lines = array_map(static fn (array $row): string => implode(' | ', $row), $payload);
send_pdf_download('clientes.pdf', 'Exportacion de clientes', $lines);
