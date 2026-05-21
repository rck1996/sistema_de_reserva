<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['id_professional']) || (string) ($_SESSION['id_estado'] ?? '') !== '2') {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'Sesion profesional requerida'));
    exit;
}

$pdo = app_pdo();
$professionalId = request_session_int('id_professional');

echo json_encode(array(
    'ok' => true,
    'csrfToken' => csrf_token(),
    'profile' => array(
        'id' => $professionalId,
        'name' => (string) ($_SESSION['name_professional'] ?? 'Profesional'),
        'scheduleSummary' => professional_schedule_summary($pdo, $professionalId),
    ),
    'hours' => business_hours(),
    'customers' => fetch_all($pdo->prepare('SELECT id_cliente AS id, nombre_cliente AS firstName, apellido_cliente AS lastName FROM clientes ORDER BY nombre_cliente, apellido_cliente')),
    'services' => fetch_all($pdo->prepare(
        'SELECT servicios.id_servicio AS id, servicios.nombre_servicio AS name, servicios.precio_servicio AS price, servicios.duracion_minutos AS durationMinutes, servicios.color
         FROM servicios
         JOIN professional_services ON professional_services.id_servicio = servicios.id_servicio
         WHERE servicios.activo = 1 AND professional_services.id_professional = :id_professional
         ORDER BY servicios.nombre_servicio'
    ), array(':id_professional' => $professionalId)),
    'bookings' => fetch_all($pdo->prepare(
        'SELECT eventos.id_evento AS id, eventos.id_cliente AS customerId, eventos.id_servicio AS serviceId,
                eventos.start, eventos.end, eventos.estado_reserva AS status, eventos.notas_reserva AS notes,
                clientes.nombre_cliente || " " || clientes.apellido_cliente AS customerName,
                servicios.nombre_servicio AS serviceName, servicios.precio_servicio AS revenue, servicios.color
         FROM eventos
         JOIN clientes ON eventos.id_cliente = clientes.id_cliente
         JOIN servicios ON eventos.id_servicio = servicios.id_servicio
         WHERE eventos.id_professional = :id_professional
         ORDER BY eventos.start ASC'
    ), array(':id_professional' => $professionalId)),
));
