<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['id_admin']) || (string) ($_SESSION['id_estado'] ?? '') !== '1') {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'authenticated' => false, 'error' => 'Sesion de administrador requerida'));
    exit;
}

$pdo = app_pdo();

$bookings = fetch_all(
    $pdo->prepare(
        'SELECT eventos.id_evento, eventos.id_professional, eventos.id_cliente, eventos.id_servicio,
                eventos.start, eventos.end, eventos.estado_reserva, eventos.notas_reserva,
                clientes.nombre_cliente, clientes.apellido_cliente,
                professionals.name_professional, professionals.calendar_color,
                servicios.nombre_servicio, servicios.precio_servicio, servicios.duracion_minutos, servicios.color, servicios.textColor,
                disciplinas.nombre_disciplina
         FROM eventos
         JOIN clientes ON eventos.id_cliente = clientes.id_cliente
         JOIN professionals ON eventos.id_professional = professionals.id_professional
         JOIN servicios ON eventos.id_servicio = servicios.id_servicio
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         ORDER BY eventos.start ASC'
    )
);

$professionals = fetch_all(
    $pdo->prepare(
        'SELECT professionals.id_professional, professionals.name_professional, professionals.activo,
                professionals.calendar_color, professionals.booking_capacity,
                GROUP_CONCAT(DISTINCT professional_disciplines.id_disciplina) AS discipline_ids,
                GROUP_CONCAT(DISTINCT professional_services.id_servicio) AS service_ids
         FROM professionals
         LEFT JOIN professional_disciplines ON professional_disciplines.id_professional = professionals.id_professional
         LEFT JOIN professional_services ON professional_services.id_professional = professionals.id_professional
         GROUP BY professionals.id_professional
         ORDER BY professionals.name_professional'
    )
);

$services = fetch_all(
    $pdo->prepare(
        'SELECT servicios.id_servicio, servicios.id_disciplina, servicios.nombre_servicio,
                servicios.precio_servicio, servicios.duracion_minutos, servicios.color, servicios.activo,
                disciplinas.nombre_disciplina
         FROM servicios
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         ORDER BY servicios.nombre_servicio'
    )
);

$now = new DateTimeImmutable('now');
$activeReservations = 0;
$revenue = 0.0;
$cancelled = 0;
$upcoming = 0;
$professionalCounts = array();

foreach ($bookings as $booking) {
    $status = (string) ($booking['estado_reserva'] ?? 'confirmada');
    if (!in_array($status, array('cancelada', 'completada'), true)) {
        $activeReservations++;
    }
    if ($status === 'cancelada') {
        $cancelled++;
    }
    if ($status !== 'cancelada' && new DateTimeImmutable((string) $booking['start']) >= $now) {
        $upcoming++;
    }
    if (in_array($status, array('confirmada', 'completada', 'en_progreso'), true)) {
        $revenue += (float) ($booking['precio_servicio'] ?? 0);
    }
    $professionalId = (string) $booking['id_professional'];
    $professionalCounts[$professionalId] = ($professionalCounts[$professionalId] ?? 0) + 1;
}

$maxProfessionalReservations = max(array(1, ...array_values($professionalCounts)));
$professionalPayload = array_map(
    static function (array $professional) use ($professionalCounts, $maxProfessionalReservations): array {
        $id = (string) $professional['id_professional'];
        $count = (int) ($professionalCounts[$id] ?? 0);

        return array(
            'id' => $id,
            'name' => (string) $professional['name_professional'],
            'role' => ((int) $professional['activo'] === 1) ? 'Profesional activo' : 'Profesional inactivo',
            'disciplines' => array_values(array_filter(explode(',', (string) ($professional['discipline_ids'] ?? '')))),
            'services' => array_values(array_filter(explode(',', (string) ($professional['service_ids'] ?? '')))),
            'utilization' => min(100, (int) round(($count / $maxProfessionalReservations) * 100)),
            'status' => ((int) $professional['activo'] === 1) ? ($count > 0 ? 'busy' : 'available') : 'offline',
            'color' => (string) ($professional['calendar_color'] ?? '#22d3ee'),
            'capacity' => (int) ($professional['booking_capacity'] ?? 1),
        );
    },
    $professionals
);

$bookingPayload = array_map(
    static fn (array $booking): array => array(
        'id' => (string) $booking['id_evento'],
        'customerName' => trim((string) $booking['nombre_cliente'] . ' ' . (string) $booking['apellido_cliente']),
        'professionalId' => (string) $booking['id_professional'],
        'professionalName' => (string) $booking['name_professional'],
        'serviceId' => (string) $booking['id_servicio'],
        'serviceName' => (string) $booking['nombre_servicio'],
        'disciplineName' => (string) ($booking['nombre_disciplina'] ?: 'General'),
        'status' => reservation_status_to_frontend((string) $booking['estado_reserva']),
        'start' => (new DateTimeImmutable((string) $booking['start']))->format(DateTimeInterface::ATOM),
        'end' => (new DateTimeImmutable((string) $booking['end']))->format(DateTimeInterface::ATOM),
        'notes' => (string) ($booking['notas_reserva'] ?? ''),
        'revenue' => (float) ($booking['precio_servicio'] ?? 0),
        'color' => (string) ($booking['color'] ?: ($booking['calendar_color'] ?: '#22d3ee')),
    ),
    $bookings
);

$servicePayload = array_map(
    static fn (array $service): array => array(
        'id' => (string) $service['id_servicio'],
        'disciplineId' => (string) ($service['id_disciplina'] ?? ''),
        'disciplineName' => (string) ($service['nombre_disciplina'] ?: 'General'),
        'name' => (string) $service['nombre_servicio'],
        'durationMinutes' => (int) $service['duracion_minutos'],
        'price' => (float) $service['precio_servicio'],
        'color' => (string) ($service['color'] ?: '#22d3ee'),
        'active' => (int) $service['activo'] === 1,
    ),
    $services
);

echo json_encode(
    array(
        'ok' => true,
        'authenticated' => true,
        'csrfToken' => csrf_token(),
        'metrics' => array(
            'activeBookings' => $activeReservations,
            'estimatedRevenue' => $revenue,
            'cancelledBookings' => $cancelled,
            'upcomingBookings' => $upcoming,
        ),
        'bookings' => $bookingPayload,
        'professionals' => $professionalPayload,
        'services' => $servicePayload,
    )
);

function reservation_status_to_frontend(string $status): string
{
    return match ($status) {
        'pendiente' => 'pending',
        'confirmada' => 'confirmed',
        'en_progreso' => 'in_progress',
        'completada' => 'completed',
        'no_asistio' => 'no_show',
        'cancelada' => 'cancelled',
        default => 'confirmed',
    };
}
