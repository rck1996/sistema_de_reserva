<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$theme = current_theme();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso profesional</title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => setting_value('brand_favicon', ''),
            'body_background' => 'radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 18%, transparent), transparent 28%), linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%)',
        )
    ); ?>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <main class="auth-shell grid gap-6 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
        <section class="surface-card surface-card-body">
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Agenda profesional</div>
            <h1 class="mt-4 text-4xl font-semibold leading-tight">Tu calendario, reservas y detalle del cliente en una sola vista.</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Cada profesional puede revisar agenda, abrir detalles de cada reserva y crear nuevos bloques sin salir del panel.</p>
        </section>
        <section class="surface-card surface-card-body">
            <a href="index.php" class="text-sm font-medium text-slate-500 transition hover:text-slate-900">← Volver al inicio</a>
            <h2 class="mt-6 text-3xl font-semibold">Entrar a mi agenda</h2>
            <form action="auth.php" method="post" class="mt-8 space-y-5">
                <?php echo csrf_input(); ?>
                <label class="block text-sm font-medium text-slate-600">Usuario
                    <input class="field-input" name="username" required>
                </label>
                <label class="block text-sm font-medium text-slate-600">Contraseña
                    <input class="field-input" name="password" type="password" required>
                </label>
                <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit" name="action" value="login-staff">
                    Abrir agenda
                </button>
            </form>
            <a href="index.php#acceso" class="mt-4 inline-flex text-sm font-medium text-slate-500 hover:text-slate-900">Recuperar acceso</a>
        </section>
    </main>
</body>
</html>
