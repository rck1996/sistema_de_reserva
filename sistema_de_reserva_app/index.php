<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

redirect_to_frontend('/');

$pdo = app_pdo();
$theme = current_theme();
$settings = app_settings($pdo);
$brandLogo = trim((string) ($settings['brand_logo'] ?? ''));
$brandFavicon = trim((string) ($settings['brand_favicon'] ?? ''));
$brandCover = trim((string) ($settings['brand_cover'] ?? ''));
$disciplinas = fetch_all($pdo->prepare('SELECT * FROM disciplinas WHERE activa = 1 ORDER BY nombre_disciplina'));
$servicios = fetch_all(
    $pdo->prepare(
        'SELECT servicios.*, disciplinas.nombre_disciplina
         FROM servicios
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         WHERE servicios.activo = 1
         ORDER BY servicios.nombre_servicio'
    )
);
$profesionales = fetch_all(
    $pdo->prepare(
        'SELECT professionals.*, disciplinas.nombre_disciplina, disciplinas.color_disciplina
         FROM professionals
         LEFT JOIN disciplinas ON professionals.id_disciplina = disciplinas.id_disciplina
         WHERE professionals.activo = 1
         ORDER BY professionals.name_professional'
    )
);
$hours = business_hours();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escape_html(app_brand_name()); ?></title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => $brandFavicon,
            'body_background' => 'radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 18%, transparent), transparent 28%), radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 16%, transparent), transparent 24%), linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%)',
        )
    ); ?>
    <style>
        .panel {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(148, 163, 184, 0.22);
        }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <header class="sticky top-0 z-40 border-b border-white/40 bg-white/70 backdrop-blur-xl">
        <div class="app-shell flex flex-wrap items-center justify-between gap-4 py-4">
            <a href="#inicio" class="flex items-center gap-3">
                <?php if ($brandLogo !== ''): ?>
                    <img class="h-11 w-11 rounded-2xl object-cover shadow-soft" src="assets/branding/<?php echo escape_html($brandLogo); ?>" alt="<?php echo escape_html(app_brand_name()); ?>">
                <?php else: ?>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-sm font-semibold text-white shadow-soft" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">
                        SR
                    </span>
                <?php endif; ?>
                <div>
                    <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Plataforma</div>
                    <div class="text-base font-semibold"><?php echo escape_html(app_display_name()); ?></div>
                </div>
            </a>
            <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                <a href="#disciplinas" class="transition hover:text-slate-950">Disciplinas</a>
                <a href="#servicios" class="transition hover:text-slate-950">Servicios</a>
                <a href="#profesionales" class="transition hover:text-slate-950">Equipo</a>
                <a href="#acceso" class="transition hover:text-slate-950">Acceso</a>
                <a href="staff-login.php" class="rounded-full border border-slate-200 px-4 py-2 transition hover:border-slate-300 hover:bg-white">Profesional</a>
                <a href="admin-login.php" class="rounded-full px-4 py-2 font-semibold text-white shadow-soft" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">Admin</a>
            </nav>
        </div>
    </header>

    <main>
        <section id="inicio" class="app-shell grid gap-8 py-10 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:py-16">
            <div class="panel rounded-[2rem] p-6 shadow-panel sm:p-10">
                <?php if ($brandCover !== ''): ?>
                    <div class="mb-6 overflow-hidden rounded-[1.5rem]">
                        <img class="h-52 w-full object-cover" src="assets/branding/<?php echo escape_html($brandCover); ?>" alt="<?php echo escape_html(app_brand_name()); ?>">
                    </div>
                <?php endif; ?>
                <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.26em] text-slate-600">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: var(--primary);"></span>
                    <?php echo escape_html($settings['business_type'] ?? 'Centro de servicios'); ?>
                </div>
                <h1 class="max-w-4xl text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl lg:text-6xl">
                    <?php echo escape_html($settings['hero_title'] ?? 'Sistema de reservas configurable y multidisciplinario.'); ?>
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                    <?php echo escape_html($settings['hero_subtitle'] ?? 'Gestiona agendas, clientes, servicios y profesionales con una experiencia moderna y lista para personalizar.'); ?>
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="#acceso" class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:opacity-95" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">
                        Crear cliente
                    </a>
                    <a href="#servicios" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                        Explorar servicios
                    </a>
                </div>
                <div class="mt-8 flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600">Horario <?php echo escape_html($hours['opening']); ?> - <?php echo escape_html($hours['closing']); ?></span>
                    <span class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600">Intervalos de <?php echo escape_html((string) $hours['slot_interval']); ?> min</span>
                    <span class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600"><?php echo escape_html($settings['booking_notice'] ?? 'Reserva online disponible.'); ?></span>
                </div>
            </div>

            <div class="grid gap-4">
                <div class="panel rounded-[2rem] p-6 shadow-panel">
                    <div class="text-sm uppercase tracking-[0.24em] text-slate-500">Nombre visible</div>
                    <div class="mt-3 text-3xl font-semibold"><?php echo escape_html(app_display_name()); ?></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="panel rounded-[2rem] p-6 shadow-soft">
                        <div class="text-sm text-slate-500">Disciplinas activas</div>
                        <div class="mt-3 text-4xl font-semibold"><?php echo count($disciplinas); ?></div>
                    </div>
                    <div class="panel rounded-[2rem] p-6 shadow-soft">
                        <div class="text-sm text-slate-500">Profesionales activos</div>
                        <div class="mt-3 text-4xl font-semibold"><?php echo count($profesionales); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="disciplinas" class="app-shell py-6">
            <div class="mb-6 flex flex-col gap-2">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Especialidades</div>
                <h2 class="text-3xl font-semibold">Disciplinas listas para configurar</h2>
                <p class="max-w-3xl text-slate-600">La plataforma ya no depende de un solo rubro. Puedes reorganizar servicios y equipo desde la configuración.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($disciplinas as $disciplina): ?>
                    <article class="panel rounded-[1.75rem] p-6 shadow-soft">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" style="background: <?php echo escape_html($disciplina['color_disciplina']); ?>22; color: <?php echo escape_html($disciplina['color_disciplina']); ?>;">
                            <?php echo escape_html($disciplina['nombre_disciplina']); ?>
                        </span>
                        <h3 class="mt-4 text-xl font-semibold"><?php echo escape_html($disciplina['nombre_disciplina']); ?></h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600"><?php echo escape_html($disciplina['descripcion_disciplina']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="servicios" class="app-shell py-6">
            <div class="mb-6 flex flex-col gap-2">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Catálogo</div>
                <h2 class="text-3xl font-semibold">Servicios configurables y más visuales</h2>
                <p class="max-w-3xl text-slate-600">Cada servicio expone disciplina, duración, precio y una presentación adaptable a la identidad futura del negocio.</p>
            </div>
            <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($servicios as $servicio): ?>
                    <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-soft">
                        <div class="aspect-[16/10] overflow-hidden bg-slate-200">
                            <img class="h-full w-full object-cover" src="assets/services/<?php echo escape_html($servicio['img_servicio']); ?>" alt="<?php echo escape_html($servicio['nombre_servicio']); ?>">
                        </div>
                        <div class="p-6" style="background: linear-gradient(180deg, <?php echo escape_html($servicio['color']); ?>f2, <?php echo escape_html($servicio['color']); ?>); color: <?php echo escape_html($servicio['textColor']); ?>;">
                            <?php if (!empty($servicio['nombre_disciplina'])): ?>
                                <span class="inline-flex rounded-full border border-white/30 px-3 py-1 text-xs font-semibold"><?php echo escape_html($servicio['nombre_disciplina']); ?></span>
                            <?php endif; ?>
                            <div class="mt-4 flex items-start justify-between gap-4">
                                <h3 class="text-xl font-semibold"><?php echo escape_html($servicio['nombre_servicio']); ?></h3>
                                <span class="rounded-full bg-white/15 px-3 py-1 text-sm font-semibold"><?php echo escape_html((string) $servicio['duracion_minutos']); ?> min</span>
                            </div>
                            <p class="mt-3 text-sm leading-7 opacity-90"><?php echo escape_html($servicio['descripcion_servicio']); ?></p>
                            <div class="mt-5 flex items-center justify-between text-sm">
                                <span class="rounded-full bg-white/15 px-3 py-2 font-semibold">$<?php echo escape_html((string) $servicio['precio_servicio']); ?></span>
                                <span><?php echo escape_html($servicio['modalidad_servicio']); ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="profesionales" class="app-shell py-6">
            <div class="mb-6 flex flex-col gap-2">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Equipo</div>
                <h2 class="text-3xl font-semibold">Profesionales visibles por especialidad</h2>
                <p class="max-w-3xl text-slate-600">La reserva puede tomar mejores decisiones cuando cliente y equipo ven claramente roles, disciplina y disponibilidad.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($profesionales as $profesional): ?>
                    <article class="panel rounded-[1.75rem] p-6 shadow-soft">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-semibold"><?php echo escape_html($profesional['name_professional']); ?></h3>
                                <p class="mt-3 text-sm leading-7 text-slate-600"><?php echo escape_html($profesional['bio_professional'] ?: 'Profesional activo en la plataforma.'); ?></p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" style="background: <?php echo escape_html(($profesional['color_disciplina'] ?? '#0f766e')); ?>22; color: <?php echo escape_html(($profesional['color_disciplina'] ?? '#0f766e')); ?>;">
                                <?php echo escape_html($profesional['nombre_disciplina'] ?: 'General'); ?>
                            </span>
                        </div>
                        <div class="mt-5 space-y-2 text-sm text-slate-500">
                            <div><?php echo escape_html($profesional['email_professional']); ?></div>
                            <div><?php echo escape_html($profesional['phone_professional']); ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="acceso" class="app-shell py-8 lg:pb-16">
            <div class="mb-6 flex flex-col gap-2">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Acceso</div>
                <h2 class="text-3xl font-semibold">Registro y acceso con una UX más clara</h2>
                <p class="max-w-3xl text-slate-600">Alta rápida de clientes, acceso directo al panel y datos de contacto visibles sin saturar la pantalla.</p>
            </div>
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <section class="surface-card surface-card-body">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Nuevo cliente</div>
                            <h3 class="mt-2 text-2xl font-semibold">Crear cuenta</h3>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-500">Acceso inmediato</span>
                    </div>
                    <form action="auth.php" method="post" class="mt-6 grid gap-4 sm:grid-cols-2">
                        <?php echo csrf_input(); ?>
                        <label class="block text-sm font-medium text-slate-600">Nombre
                            <input class="field-input" name="first_name" required>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Apellido
                            <input class="field-input" name="last_name" required>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Telefono
                            <input class="field-input" name="phone" placeholder="+56900000000" required>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Correo
                            <input class="field-input" name="email" type="email" required>
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Usuario opcional
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-400" name="username" placeholder="Se genera automáticamente si lo dejas vacío">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Contraseña
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-400" name="password" type="password" minlength="8" required>
                        </label>
                        <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:opacity-95" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit" name="action" value="register-customer">
                            Crear cuenta
                        </button>
                    </form>
                </section>

                <section class="grid gap-6">
                    <div class="surface-card surface-card-body">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ingreso cliente</div>
                        <h3 class="mt-2 text-2xl font-semibold">Entrar al panel</h3>
                        <form action="auth.php" method="post" class="mt-6 space-y-4">
                            <?php echo csrf_input(); ?>
                            <label class="block text-sm font-medium text-slate-600">Usuario
                            <input class="field-input" name="username" required>
                            </label>
                            <label class="block text-sm font-medium text-slate-600">Contraseña
                            <input class="field-input" name="password" type="password" required>
                            </label>
                            <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:opacity-95" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit" name="action" value="login-customer">
                                Entrar al panel
                            </button>
                        </form>
                        <form action="auth.php" method="post" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="request-password-reset">
                            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Recuperar acceso</div>
                            <label class="block text-sm font-medium text-slate-600">Correo o usuario
                                <input class="field-input" name="identity" required>
                            </label>
                            <button class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-900" type="submit">
                                Generar enlace de recuperación
                            </button>
                        </form>
                    </div>
                    <div class="surface-card surface-card-body">
                        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Contacto</div>
                                <div class="mt-3 space-y-1 text-sm text-slate-600">
                                    <div><?php echo escape_html($settings['contact_email'] ?? 'contacto@sistema.local'); ?></div>
                                    <div><?php echo escape_html($settings['contact_phone'] ?? '+56900000000'); ?></div>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ubicación</div>
                                <div class="mt-3 space-y-1 text-sm text-slate-600">
                                    <div><?php echo escape_html($settings['contact_address'] ?? 'Configura tu dirección'); ?></div>
                                    <div><?php echo escape_html($settings['business_city'] ?? 'Santiago de Chile'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </main>
</body>
</html>
