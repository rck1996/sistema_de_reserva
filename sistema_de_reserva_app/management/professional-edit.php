<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');

$pdo = app_pdo();
$theme = current_theme();
$professionalId = request_query_int('id_professional');
$profesional = fetch_one($pdo->prepare('SELECT * FROM professionals WHERE id_professional = :id'), array(':id' => $professionalId));
$disciplinas = fetch_all($pdo->prepare('SELECT * FROM disciplinas ORDER BY nombre_disciplina'));
$schedule = professional_weekly_schedule($pdo, $professionalId);
$exceptions = fetch_all(
    $pdo->prepare('SELECT * FROM professional_exceptions WHERE id_professional = :id_professional ORDER BY exception_date DESC LIMIT 5'),
    array(':id_professional' => $professionalId)
);

if ($profesional === null) { app_redirect('../admin-dashboard.php#equipo', 'Profesional no encontrado'); }
$weekdayLabels = weekday_labels();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar profesional</title><script src="https://cdn.tailwindcss.com"></script>
<style>:root{--primary:<?php echo escape_html($theme['primary']); ?>;--secondary:<?php echo escape_html($theme['secondary']); ?>;}body{background:linear-gradient(180deg,#f8fafc 0%,#edf2f7 100%);}</style>
</head>
<body class="min-h-screen px-4 py-8 text-slate-900 sm:px-6">
<main class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-[2rem] border border-white/50 bg-white/85 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
        <a class="text-sm font-medium text-slate-500 hover:text-slate-900" href="../admin-dashboard.php#equipo">← Volver</a>
        <h1 class="mt-4 text-3xl font-semibold">Editar profesional</h1>
        <form action="update.php?id_professional=<?php echo escape_html((string) $profesional['id_professional']); ?>&accion=profesional" method="post" class="mt-6 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-600">Nombre<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="name_professional" value="<?php echo escape_html($profesional['name_professional']); ?>" required></label>
            <label class="block text-sm font-medium text-slate-600">Usuario<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="user_professional" value="<?php echo escape_html($profesional['user_professional']); ?>" required></label>
            <label class="block text-sm font-medium text-slate-600">Correo<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="email_professional" type="email" value="<?php echo escape_html($profesional['email_professional']); ?>" required></label>
            <label class="block text-sm font-medium text-slate-600">Telefono<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="phone_professional" value="<?php echo escape_html($profesional['phone_professional']); ?>" required></label>
            <label class="block text-sm font-medium text-slate-600">Disciplina<select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="id_disciplina"><option value="">General</option><?php foreach ($disciplinas as $disciplina): ?><option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>" <?php echo (int) ($profesional['id_disciplina'] ?? 0) === (int) $disciplina['id_disciplina'] ? 'selected' : ''; ?>><?php echo escape_html($disciplina['nombre_disciplina']); ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm font-medium text-slate-600">Color<input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="calendar_color" type="color" value="<?php echo escape_html($profesional['calendar_color']); ?>" required></label>
            <label class="block text-sm font-medium text-slate-600">Nueva contraseña<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="pass_professional" type="password" minlength="8"></label>
            <label class="block text-sm font-medium text-slate-600">Activo<select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="activo"><option value="1" <?php echo (int) $profesional['activo'] === 1 ? 'selected' : ''; ?>>Sí</option><option value="0" <?php echo (int) $profesional['activo'] === 0 ? 'selected' : ''; ?>>No</option></select></label>
            <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Bio<textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="bio_professional"><?php echo escape_html($profesional['bio_professional']); ?></textarea></label>
            <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background:linear-gradient(135deg,var(--secondary),var(--primary));" type="submit">Guardar cambios</button>
        </form>
    </section>

    <section class="rounded-[2rem] border border-white/50 bg-white/85 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Disponibilidad</div>
        <h2 class="mt-3 text-2xl font-semibold">Horario semanal y excepciones</h2>
        <form action="update.php?id_professional=<?php echo escape_html((string) $professionalId); ?>&accion=professional_schedule" method="post" class="mt-6 space-y-5">
            <?php foreach ($weekdayLabels as $weekday => $label): $row = $schedule[$weekday]; ?>
                <div class="grid gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_repeat(5,minmax(0,1fr))]">
                    <label class="block text-sm font-medium text-slate-700">
                        <span class="block"><?php echo escape_html($label); ?></span>
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="is_working_<?php echo $weekday; ?>">
                            <option value="1" <?php echo (int) $row['is_working'] === 1 ? 'selected' : ''; ?>>Disponible</option>
                            <option value="0" <?php echo (int) $row['is_working'] === 0 ? 'selected' : ''; ?>>Libre</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Inicio<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="start_time_<?php echo $weekday; ?>" type="time" value="<?php echo escape_html($row['start_time']); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Termino<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="end_time_<?php echo $weekday; ?>" type="time" value="<?php echo escape_html($row['end_time']); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Pausa inicio<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="break_start_<?php echo $weekday; ?>" type="time" value="<?php echo escape_html($row['break_start']); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Pausa fin<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="break_end_<?php echo $weekday; ?>" type="time" value="<?php echo escape_html($row['break_end']); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Intervalo<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="slot_interval_<?php echo $weekday; ?>" type="number" min="5" step="5" value="<?php echo escape_html((string) $row['slot_interval']); ?>"></label>
                </div>
            <?php endforeach; ?>
            <div class="grid gap-4 rounded-3xl border border-dashed border-slate-300 p-4 md:grid-cols-2">
                <label class="block text-sm font-medium text-slate-600">Fecha excepcional<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="exception_date" type="date"></label>
                <label class="block text-sm font-medium text-slate-600">Modo<select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="exception_day_off"><option value="0">Horario especial</option><option value="1">Dia libre</option></select></label>
                <label class="block text-sm font-medium text-slate-600">Inicio excepcional<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="exception_start_time" type="time"></label>
                <label class="block text-sm font-medium text-slate-600">Termino excepcional<input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="exception_end_time" type="time"></label>
                <label class="block text-sm font-medium text-slate-600 md:col-span-2">Notas<textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="exception_notes"></textarea></label>
            </div>
            <button class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background:linear-gradient(135deg,var(--secondary),var(--primary));" type="submit">Guardar disponibilidad</button>
        </form>
        <?php if ($exceptions !== array()): ?>
            <div class="mt-6 space-y-3">
                <?php foreach ($exceptions as $exception): ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        <div class="font-semibold text-slate-900"><?php echo escape_html($exception['exception_date']); ?></div>
                        <div><?php echo (int) $exception['is_day_off'] === 1 ? 'Dia libre' : escape_html(($exception['start_time'] ?: '--') . ' - ' . ($exception['end_time'] ?: '--')); ?></div>
                        <div><?php echo escape_html($exception['notes']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
