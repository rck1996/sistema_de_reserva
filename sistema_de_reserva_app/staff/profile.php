<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('id_professional', '2', '../staff-login.php');

$pdo = app_pdo();
$theme = current_theme();
$profesional = fetch_one(
    $pdo->prepare('SELECT * FROM professionals WHERE id_professional = :id'),
    array(':id' => request_session_int('id_professional'))
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi perfil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: <?php echo escape_html($theme['primary']); ?>;
            --accent: <?php echo escape_html($theme['accent']); ?>;
        }
        body {
            background: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
        }
    </style>
</head>
<body class="min-h-screen px-4 py-8 text-slate-900 sm:px-6">
    <main class="mx-auto max-w-3xl rounded-[2rem] border border-white/50 bg-white/85 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
        <a class="text-sm font-medium text-slate-500 hover:text-slate-900" href="../staff-dashboard.php">← Volver</a>
        <h1 class="mt-4 text-3xl font-semibold">Editar mis datos</h1>
        <form action="update.php" method="post" class="mt-6 grid gap-4">
            <?php echo csrf_input(); ?>
            <label class="block text-sm font-medium text-slate-600">
                Nombre
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="name_professional" value="<?php echo escape_html($profesional['name_professional'] ?? ''); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Usuario
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="user_professional" value="<?php echo escape_html($profesional['user_professional'] ?? ''); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Nueva contraseña
                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="pass_professional" type="password" minlength="8">
            </label>
            <button class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">
                Guardar cambios
            </button>
        </form>
    </main>
</body>
</html>
