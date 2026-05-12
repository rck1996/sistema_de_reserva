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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: <?php echo escape_html($theme['primary']); ?>;
            --secondary: <?php echo escape_html($theme['secondary']); ?>;
            --accent: <?php echo escape_html($theme['accent']); ?>;
        }
        body {
            background:
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 18%, transparent), transparent 28%),
                linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
        }
    </style>
</head>
<body class="min-h-screen px-4 py-10 text-slate-900 sm:px-6">
    <main class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[0.92fr_1.08fr]">
        <section class="rounded-[2rem] border border-white/50 bg-white/80 p-8 shadow-2xl backdrop-blur-xl">
            <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Agenda profesional</div>
            <h1 class="mt-4 text-4xl font-semibold leading-tight">Tu calendario, reservas y detalle del cliente en una sola vista.</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Cada profesional puede revisar agenda, abrir detalles de cada reserva y crear nuevos bloques sin salir del panel.</p>
        </section>
        <section class="rounded-[2rem] border border-white/50 bg-white/85 p-8 shadow-2xl backdrop-blur-xl">
            <a href="index.php" class="text-sm font-medium text-slate-500 transition hover:text-slate-900">← Volver al inicio</a>
            <h2 class="mt-6 text-3xl font-semibold">Entrar a mi agenda</h2>
            <form action="auth.php" method="post" class="mt-8 space-y-5">
                <label class="block text-sm font-medium text-slate-600">Usuario
                    <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" name="username" required>
                </label>
                <label class="block text-sm font-medium text-slate-600">Contraseña
                    <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" name="password" type="password" required>
                </label>
                <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit" name="action" value="login-staff">
                    Abrir agenda
                </button>
            </form>
        </section>
    </main>
</body>
</html>
