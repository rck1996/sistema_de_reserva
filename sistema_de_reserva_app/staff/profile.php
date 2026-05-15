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
    <?php render_shared_head_assets($theme, array('body_background' => 'linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%)')); ?>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <main class="auth-shell">
        <section class="auth-card max-w-3xl">
        <a class="text-sm font-medium text-slate-500 hover:text-slate-900" href="../staff-dashboard.php">← Volver</a>
        <h1 class="mt-4 text-3xl font-semibold">Editar mis datos</h1>
        <form action="update.php" method="post" class="mt-6 grid gap-4">
            <?php echo csrf_input(); ?>
            <label class="block text-sm font-medium text-slate-600">
                Nombre
                <input class="field-input" name="name_professional" value="<?php echo escape_html($profesional['name_professional'] ?? ''); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Usuario
                <input class="field-input" name="user_professional" value="<?php echo escape_html($profesional['user_professional'] ?? ''); ?>" required>
            </label>
            <label class="block text-sm font-medium text-slate-600">
                Nueva contraseña
                <input class="field-input" name="pass_professional" type="password" minlength="8">
            </label>
            <button class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">
                Guardar cambios
            </button>
        </form>
        </section>
    </main>
</body>
</html>
