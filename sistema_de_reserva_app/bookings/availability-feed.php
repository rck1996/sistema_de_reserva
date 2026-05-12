<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = app_pdo();
$professionalId = 0;

if (!empty($_SESSION['id_professional']) && (string) ($_SESSION['id_estado'] ?? '') === '2') {
    $professionalId = request_session_int('id_professional');
} else {
    $professionalId = request_query_int('professional_id');
}

$startRaw = trim((string) ($_GET['start'] ?? ''));
$endRaw = trim((string) ($_GET['end'] ?? ''));
$start = $startRaw !== '' ? new DateTimeImmutable($startRaw) : new DateTimeImmutable('today');
$end = $endRaw !== '' ? new DateTimeImmutable($endRaw) : $start->modify('+30 days');

echo json_encode(professional_availability_background_events($pdo, $professionalId, $start, $end));
