<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['id_cliente']) || (string) ($_SESSION['id_estado'] ?? '') !== '3') {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'Sesion de cliente requerida'));
    exit;
}

$pdo = app_pdo();
$customerId = request_session_int('id_cliente');

echo json_encode(array(
    'ok' => true,
    'csrfToken' => csrf_token(),
    'profile' => array(
        'id' => $customerId,
        'name' => trim((string) ($_SESSION['nombre_cliente'] ?? '') . ' ' . (string) ($_SESSION['apellido_cliente'] ?? '')),
    ),
    'hours' => business_hours(),
    'disciplines' => fetch_all($pdo->prepare('SELECT id_disciplina AS id, nombre_disciplina AS name FROM disciplinas WHERE activa = 1 ORDER BY nombre_disciplina')),
    'services' => fetch_all($pdo->prepare('SELECT id_servicio AS id, id_disciplina AS disciplineId, nombre_servicio AS name, precio_servicio AS price, duracion_minutos AS durationMinutes, color FROM servicios WHERE activo = 1 ORDER BY nombre_servicio')),
    'professionals' => fetch_all($pdo->prepare(
        'SELECT professionals.id_professional AS id, professionals.name_professional AS name,
                GROUP_CONCAT(DISTINCT professional_services.id_servicio) AS serviceIds,
                GROUP_CONCAT(DISTINCT professional_disciplines.id_disciplina) AS disciplineIds
         FROM professionals
         LEFT JOIN professional_services ON professional_services.id_professional = professionals.id_professional
         LEFT JOIN professional_disciplines ON professional_disciplines.id_professional = professionals.id_professional
         WHERE professionals.activo = 1
         GROUP BY professionals.id_professional
         ORDER BY professionals.name_professional'
    )),
    'bookings' => fetch_all($pdo->prepare(
        'SELECT eventos.id_evento AS id, eventos.id_professional AS professionalId, eventos.id_servicio AS serviceId,
                eventos.start, eventos.end, eventos.estado_reserva AS status, eventos.notas_reserva AS notes,
                professionals.name_professional AS professionalName, servicios.nombre_servicio AS serviceName, servicios.precio_servicio AS revenue, servicios.color
         FROM eventos
         JOIN professionals ON eventos.id_professional = professionals.id_professional
         JOIN servicios ON eventos.id_servicio = servicios.id_servicio
         WHERE eventos.id_cliente = :id_cliente
         ORDER BY eventos.start ASC'
    ), array(':id_cliente' => $customerId)),
));
