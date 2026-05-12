<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_cliente', '3', '../index.php');

$pdo = app_pdo();
$theme = current_theme();
$eventoId = request_query_int('id_evento');
$evento = fetch_one(
    $pdo->prepare('SELECT * FROM eventos WHERE id_evento = :id AND id_cliente = :id_cliente'),
    array(':id' => $eventoId, ':id_cliente' => request_session_int('id_cliente'))
);
$profesionales = fetch_all($pdo->prepare('SELECT id_professional, name_professional FROM professionals WHERE activo = 1 ORDER BY name_professional'));
$servicios = fetch_all($pdo->prepare('SELECT id_servicio, nombre_servicio, duracion_minutos FROM servicios WHERE activo = 1 ORDER BY nombre_servicio'));

if ($evento === null) {
    app_redirect('../customer-dashboard.php', 'Reserva no encontrada');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar reserva</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: <?php echo escape_html($theme['primary']); ?>;
            --secondary: <?php echo escape_html($theme['secondary']); ?>;
        }
        body {
            background: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
        }
    </style>
</head>
<body class="min-h-screen px-4 py-8 text-slate-900 sm:px-6">
    <main class="mx-auto max-w-3xl rounded-[2rem] border border-white/50 bg-white/85 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
        <a class="text-sm font-medium text-slate-500 hover:text-slate-900" href="../customer-dashboard.php">← Volver</a>
        <h1 class="mt-4 text-3xl font-semibold">Editar reserva</h1>
        <form action="update.php?id_evento=<?php echo escape_html((string) $eventoId); ?>&accion=evento" method="post" class="mt-6 grid gap-4 sm:grid-cols-2">
            <?php echo csrf_input(); ?>
            <label class="block text-sm font-medium text-slate-600">
                Profesional
                <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="professional_id" required>
                    <?php foreach ($profesionales as $profesional): ?>
                        <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>" <?php echo (int) $profesional['id_professional'] === (int) $evento['id_professional'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($profesional['name_professional']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Servicio
                <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="txt_servicio" required>
                    <?php foreach ($servicios as $servicio): ?>
                        <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>" <?php echo (int) $servicio['id_servicio'] === (int) $evento['id_servicio'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($servicio['nombre_servicio'] . ' · ' . $servicio['duracion_minutos'] . ' min'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Fecha
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="dia" type="date" value="<?php echo escape_html(substr((string) $evento['start'], 0, 10)); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Hora
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="hora" type="time" value="<?php echo escape_html(substr((string) $evento['start'], 11, 5)); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600 sm:col-span-2">
                Notas
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="notas_reserva" value="<?php echo escape_html($evento['notas_reserva']); ?>">
            </label>
            <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit">
                Guardar cambios
            </button>
        </form>
    </main>
</body>
</html>
