<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$theme = current_theme();
$token = trim((string) ($_GET['token'] ?? ''));
$record = $token !== '' ? password_reset_record(app_pdo(), $token) : null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contraseña</title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => setting_value('brand_favicon', ''),
            'body_background' => 'linear-gradient(180deg,#f8fafc 0%,#edf2f7 100%)',
        )
    ); ?>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <main class="auth-shell">
        <section class="auth-card max-w-xl">
        <a href="index.php#acceso" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver</a>
        <h1 class="mt-6 text-3xl font-semibold">Definir nueva contraseña</h1>
        <?php if ($record === null): ?>
            <p class="mt-4 text-sm leading-7 text-slate-600">El enlace de recuperación no es válido o ya expiró.</p>
        <?php else: ?>
            <form action="auth.php" method="post" class="mt-8 space-y-5">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="reset-password">
                <input type="hidden" name="token" value="<?php echo escape_html($token); ?>">
                <label class="block text-sm font-medium text-slate-600">Nueva contraseña
                    <input class="field-input" name="password" type="password" minlength="8" required>
                </label>
                <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background:linear-gradient(135deg,var(--secondary),var(--primary));" type="submit">Actualizar contraseña</button>
            </form>
        <?php endif; ?>
        </section>
    </main>
</body>
</html>
