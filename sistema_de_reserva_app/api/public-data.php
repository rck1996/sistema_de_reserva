<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$pdo = app_pdo();
$settings = app_settings($pdo);

echo json_encode(array(
    'ok' => true,
    'csrfToken' => csrf_token(),
    'brand' => array(
        'name' => app_brand_name(),
        'displayName' => app_display_name(),
        'tagline' => (string) ($settings['business_tagline'] ?? ''),
        'heroTitle' => (string) ($settings['hero_title'] ?? ''),
        'heroSubtitle' => (string) ($settings['hero_subtitle'] ?? ''),
        'businessType' => (string) ($settings['business_type'] ?? ''),
        'contactEmail' => (string) ($settings['contact_email'] ?? ''),
        'contactPhone' => (string) ($settings['contact_phone'] ?? ''),
    ),
    'hours' => business_hours(),
    'disciplines' => fetch_all($pdo->prepare('SELECT id_disciplina AS id, nombre_disciplina AS name, descripcion_disciplina AS description, color_disciplina AS color FROM disciplinas WHERE activa = 1 ORDER BY nombre_disciplina')),
    'services' => fetch_all($pdo->prepare('SELECT id_servicio AS id, id_disciplina AS disciplineId, nombre_servicio AS name, descripcion_servicio AS description, precio_servicio AS price, duracion_minutos AS durationMinutes, modalidad_servicio AS modality, img_servicio AS image, color FROM servicios WHERE activo = 1 ORDER BY nombre_servicio')),
    'professionals' => fetch_all($pdo->prepare('SELECT id_professional AS id, name_professional AS name, bio_professional AS bio, email_professional AS email, phone_professional AS phone, calendar_color AS color FROM professionals WHERE activo = 1 ORDER BY name_professional')),
));
