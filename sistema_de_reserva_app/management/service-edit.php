<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_admin', '1', '../admin-login.php');

$pdo = app_pdo();
$theme = current_theme();
$servicio = fetch_one($pdo->prepare('SELECT * FROM servicios WHERE id_servicio = :id'), array(':id' => request_query_int('id_servicio')));
$disciplinas = fetch_all($pdo->prepare('SELECT * FROM disciplinas ORDER BY nombre_disciplina'));

if ($servicio === null) {
    app_redirect('../admin-dashboard.php#servicios', 'Servicio no encontrado');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar servicio</title>
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
    <main class="mx-auto max-w-4xl rounded-[2rem] border border-white/50 bg-white/85 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
        <a class="text-sm font-medium text-slate-500 hover:text-slate-900" href="../admin-dashboard.php#servicios">← Volver</a>
        <h1 class="mt-4 text-3xl font-semibold">Editar servicio</h1>
        <form action="update.php?id_servicio=<?php echo escape_html((string) $servicio['id_servicio']); ?>&accion=servicio" method="post" enctype="multipart/form-data" class="mt-6 grid gap-4 sm:grid-cols-2">
            <?php echo csrf_input(); ?>
            <label class="block text-sm font-medium text-slate-600 sm:col-span-2">
                Nombre
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="nombre_servicio" value="<?php echo escape_html($servicio['nombre_servicio']); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Disciplina
                <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="id_disciplina">
                    <option value="">General</option>
                    <?php foreach ($disciplinas as $disciplina): ?>
                        <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>" <?php echo (int) ($servicio['id_disciplina'] ?? 0) === (int) $disciplina['id_disciplina'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($disciplina['nombre_disciplina']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Precio
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="precio_servicio" type="number" min="0" step="0.01" value="<?php echo escape_html((string) $servicio['precio_servicio']); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Duración
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="duracion_minutos" type="number" min="15" step="15" value="<?php echo escape_html((string) $servicio['duracion_minutos']); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Modalidad
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="modalidad_servicio" value="<?php echo escape_html($servicio['modalidad_servicio']); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Imagen nueva
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="img_servicio" type="file" accept=".jpg,.jpeg,.png,.webp">
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Color fondo
                <input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="color" type="color" value="<?php echo escape_html($servicio['color']); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Color texto
                <input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="textColor" type="color" value="<?php echo escape_html($servicio['textColor']); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Activo
                <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="activo">
                    <option value="1" <?php echo (int) $servicio['activo'] === 1 ? 'selected' : ''; ?>>Sí</option>
                    <option value="0" <?php echo (int) $servicio['activo'] === 0 ? 'selected' : ''; ?>>No</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-600 sm:col-span-2">
                Descripción
                <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="descripcion_servicio" required><?php echo escape_html($servicio['descripcion_servicio']); ?></textarea>
            </label>
            <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit">
                Guardar cambios
            </button>
        </form>
    </main>
</body>
</html>
