<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = app_pdo();
$stmt = $pdo->prepare(
    'SELECT eventos.id_evento, eventos.title, eventos.start, eventos.end, eventos.estado_reserva,
            professionals.name_professional, professionals.calendar_color,
            servicios.nombre_servicio, servicios.color, servicios.textColor,
            disciplinas.nombre_disciplina
     FROM eventos
     JOIN professionals ON eventos.id_professional = professionals.id_professional
     JOIN clientes ON eventos.id_cliente = clientes.id_cliente
     JOIN servicios ON eventos.id_servicio = servicios.id_servicio
     LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
     ORDER BY eventos.start ASC'
);

echo json_encode(fetch_all($stmt));
