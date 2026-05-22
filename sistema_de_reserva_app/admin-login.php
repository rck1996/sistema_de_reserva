<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

redirect_to_frontend('/login');

$theme = current_theme();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso administrador</title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => setting_value('brand_favicon', ''),
            'body_background' => 'radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 18%, transparent), transparent 28%), linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%)',
        )
    ); ?>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <main class="auth-shell grid gap-6 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
        <section class="surface-card surface-card-body">
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Administracion</div>
            <h1 class="mt-4 text-4xl font-semibold leading-tight">Centro de control con configuracion completa.</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Gestiona identidad, agenda, clientes, servicios y operacion general desde un unico panel.</p>
            <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="text-sm font-semibold text-slate-900">Credencial inicial</div>
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    <div>admin@sistema.local</div>
                    <div>Admin12345</div>
                </div>
            </div>
        </section>
        <section class="surface-card surface-card-body">
            <a href="index.php" class="text-sm font-medium text-slate-500 transition hover:text-slate-900">← Volver al inicio</a>
            <h2 class="mt-6 text-3xl font-semibold">Ingresar al panel</h2>
            <form action="auth.php" method="post" class="mt-8 space-y-5">
                <?php echo csrf_input(); ?>
                <?php if (($_GET['legacy'] ?? '') === '1'): ?>
                    <input type="hidden" name="legacy" value="1">
                <?php endif; ?>
                <label class="block text-sm font-medium text-slate-600">Correo administrador
                    <input class="field-input" name="email" type="email" required>
                </label>
                <label class="block text-sm font-medium text-slate-600">Contraseña
                    <input class="field-input" name="password" type="password" required>
                </label>
                <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit" name="action" value="login-admin">
                    Ingresar al panel
                </button>
            </form>
            <a href="index.php#acceso" class="mt-4 inline-flex text-sm font-medium text-slate-500 hover:text-slate-900">Recuperar acceso</a>
        </section>
    </main>
</body>
</html>
