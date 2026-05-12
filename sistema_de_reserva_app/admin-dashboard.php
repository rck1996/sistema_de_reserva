<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_role('id_admin', '1', 'admin-login.php');

$pdo = app_pdo();
$theme = current_theme();
$settings = app_settings($pdo);
$csrfToken = csrf_token();
$disciplinas = fetch_all($pdo->prepare('SELECT * FROM disciplinas ORDER BY nombre_disciplina'));
$profesionales = fetch_all(
    $pdo->prepare(
        'SELECT professionals.*, disciplinas.nombre_disciplina
         FROM professionals
         LEFT JOIN disciplinas ON professionals.id_disciplina = disciplinas.id_disciplina
         ORDER BY professionals.name_professional'
    )
);
$clientes = fetch_all($pdo->prepare('SELECT * FROM clientes ORDER BY id_cliente DESC'));
$servicios = fetch_all(
    $pdo->prepare(
        'SELECT servicios.*, disciplinas.nombre_disciplina
         FROM servicios
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         ORDER BY servicios.nombre_servicio'
    )
);
$reservas = fetch_all(
    $pdo->prepare(
        'SELECT eventos.*, clientes.nombre_cliente, clientes.apellido_cliente, clientes.telefono_cliente, professionals.name_professional,
                servicios.nombre_servicio, servicios.duracion_minutos, disciplinas.nombre_disciplina
         FROM eventos
         JOIN clientes ON eventos.id_cliente = clientes.id_cliente
         JOIN professionals ON eventos.id_professional = professionals.id_professional
         JOIN servicios ON eventos.id_servicio = servicios.id_servicio
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         ORDER BY eventos.start ASC'
    )
);
$hours = business_hours();
$brandLogo = trim((string) ($settings['brand_logo'] ?? ''));
$brandFavicon = trim((string) ($settings['brand_favicon'] ?? ''));
$brandCover = trim((string) ($settings['brand_cover'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administrador | <?php echo escape_html(app_brand_name()); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
    <style>
        :root {
            --primary: <?php echo escape_html($theme['primary']); ?>;
            --secondary: <?php echo escape_html($theme['secondary']); ?>;
            --accent: <?php echo escape_html($theme['accent']); ?>;
        }
        body {
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 15%, transparent), transparent 25%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 12%, transparent), transparent 20%),
                linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
        }
        .fc .fc-toolbar-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .fc .fc-button { border-radius: 999px; border: 0; box-shadow: none; padding: 0.7rem 1rem; background: #e2e8f0; color: #0f172a; }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active { background: var(--secondary); }
        .fc .fc-scrollgrid, .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(148, 163, 184, 0.22); }
        .fc-event { border: 0; border-radius: 16px; padding: 4px 6px; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10); }
        dialog::backdrop { background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px); }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <header class="sticky top-0 z-40 border-b border-white/50 bg-white/75 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Centro de control</div>
                <h1 class="text-lg font-semibold"><?php echo escape_html(app_display_name()); ?></h1>
            </div>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="#configuracion" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300">Configuración</a>
                <a href="#profesionales" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300">Equipo</a>
                <a href="#clientes" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300">Clientes</a>
                <a href="#servicios" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300">Servicios</a>
                <a href="#reservas" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300">Reservas</a>
                <a href="logout.php" class="rounded-full px-4 py-2 font-semibold text-white" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">Cerrar sesión</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[1.75rem] border border-white/50 bg-white/85 p-6 shadow-xl backdrop-blur-xl">
                <div class="text-sm text-slate-500">Reservas</div>
                <div class="mt-3 text-4xl font-semibold"><?php echo count($reservas); ?></div>
            </article>
            <article class="rounded-[1.75rem] border border-white/50 bg-white/85 p-6 shadow-xl backdrop-blur-xl">
                <div class="text-sm text-slate-500">Profesionales</div>
                <div class="mt-3 text-4xl font-semibold"><?php echo count($profesionales); ?></div>
            </article>
            <article class="rounded-[1.75rem] border border-white/50 bg-white/85 p-6 shadow-xl backdrop-blur-xl">
                <div class="text-sm text-slate-500">Clientes</div>
                <div class="mt-3 text-4xl font-semibold"><?php echo count($clientes); ?></div>
            </article>
            <article class="rounded-[1.75rem] border border-white/50 bg-white/85 p-6 shadow-xl backdrop-blur-xl">
                <div class="text-sm text-slate-500">Horario</div>
                <div class="mt-3 text-xl font-semibold"><?php echo escape_html($hours['opening']); ?> - <?php echo escape_html($hours['closing']); ?></div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.45fr_0.8fr]">
            <div class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-2xl backdrop-blur-xl sm:p-6">
                <div class="mb-5 flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Calendario global</div>
                        <h2 class="mt-2 text-2xl font-semibold">Agenda central editable</h2>
                    </div>
                    <div class="text-sm text-slate-500">Arrastra, reprograma y gestiona estados desde el modal.</div>
                </div>
                <div id="admin-calendar"></div>
            </div>

            <div class="space-y-6">
                <section id="configuracion" class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Configuración</div>
                    <h3 class="mt-2 text-xl font-semibold">Identidad y horario</h3>
                    <form action="management/create.php?accion=configuracion" method="post" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
                        <?php echo csrf_input(); ?>
                        <label class="block text-sm font-medium text-slate-600">Nombre interno
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="app_name" value="<?php echo escape_html($settings['app_name'] ?? 'sistema_de_reserva'); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Nombre visible
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="business_name" value="<?php echo escape_html($settings['business_name'] ?? 'Sistema de Reserva'); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Tipo de negocio
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="business_type" value="<?php echo escape_html($settings['business_type'] ?? 'Centro de servicios'); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Título principal
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="hero_title" value="<?php echo escape_html($settings['hero_title'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Subtítulo principal
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="hero_subtitle" value="<?php echo escape_html($settings['hero_subtitle'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Apertura
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="opening_time" type="time" value="<?php echo escape_html($settings['opening_time'] ?? '09:00'); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Cierre
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="closing_time" type="time" value="<?php echo escape_html($settings['closing_time'] ?? '20:00'); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Intervalo
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="slot_interval" type="number" min="5" step="5" value="<?php echo escape_html($settings['slot_interval'] ?? '30'); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Correo
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="contact_email" value="<?php echo escape_html($settings['contact_email'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Teléfono
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="contact_phone" value="<?php echo escape_html($settings['contact_phone'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Dirección
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="contact_address" value="<?php echo escape_html($settings['contact_address'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Ciudad
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="business_city" value="<?php echo escape_html($settings['business_city'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Mensaje de reserva
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="booking_notice" value="<?php echo escape_html($settings['booking_notice'] ?? ''); ?>">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Logo
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="brand_logo" type="file" accept=".png,.jpg,.jpeg,.webp,.svg">
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Favicon
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="brand_favicon" type="file" accept=".png,.ico">
                        </label>
                        <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Imagen de portada
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="brand_cover" type="file" accept=".png,.jpg,.jpeg,.webp">
                        </label>
                        <div class="sm:col-span-2 grid gap-3 sm:grid-cols-3">
                            <?php if ($brandLogo !== ''): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Logo</div>
                                    <img src="<?php echo escape_html($brandLogo); ?>" alt="Logo" class="mt-3 h-20 w-full rounded-2xl object-contain">
                                </div>
                            <?php endif; ?>
                            <?php if ($brandFavicon !== ''): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Favicon</div>
                                    <img src="<?php echo escape_html($brandFavicon); ?>" alt="Favicon" class="mt-3 h-20 w-full rounded-2xl object-contain">
                                </div>
                            <?php endif; ?>
                            <?php if ($brandCover !== ''): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:col-span-<?php echo ($brandLogo === '' && $brandFavicon === '') ? '3' : '1'; ?>">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Portada</div>
                                    <img src="<?php echo escape_html($brandCover); ?>" alt="Portada" class="mt-3 h-20 w-full rounded-2xl object-cover">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit">
                            Guardar configuración
                        </button>
                    </form>
                </section>

                <section id="reservas" class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Nueva reserva</div>
                    <h3 class="mt-2 text-xl font-semibold">Crear reserva desde admin</h3>
                    <form action="management/create.php?accion=agendar_admin" method="post" class="mt-5 space-y-4">
                        <?php echo csrf_input(); ?>
                        <label class="block text-sm font-medium text-slate-600">Cliente
                            <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="txt_cliente" required>
                                <option value="">Selecciona cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo escape_html((string) $cliente['id_cliente']); ?>"><?php echo escape_html($cliente['nombre_cliente'] . ' ' . $cliente['apellido_cliente']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Profesional
                            <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="professional_id" required>
                                <option value="">Selecciona profesional</option>
                                <?php foreach ($profesionales as $profesional): ?>
                                    <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>"><?php echo escape_html($profesional['name_professional']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Servicio
                            <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="txt_servicio" required>
                                <option value="">Selecciona servicio</option>
                                <?php foreach ($servicios as $servicio): ?>
                                    <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>"><?php echo escape_html($servicio['nombre_servicio'] . ' · ' . $servicio['duracion_minutos'] . ' min'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-slate-600">Fecha
                                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="dia" type="date" required>
                            </label>
                            <label class="block text-sm font-medium text-slate-600">Hora
                                <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="hora" type="time" min="<?php echo escape_html($hours['opening']); ?>" max="<?php echo escape_html($hours['closing']); ?>" step="<?php echo escape_html((string) ($hours['slot_interval'] * 60)); ?>" required>
                            </label>
                        </div>
                        <label class="block text-sm font-medium text-slate-600">Notas
                            <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="notas_reserva"></textarea>
                        </label>
                        <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">
                            Crear reserva
                        </button>
                    </form>
                </section>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <section id="profesionales" class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Equipo</div>
                        <h3 class="mt-2 text-xl font-semibold">Profesionales</h3>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500"><?php echo count($profesionales); ?> registrados</span>
                </div>
                <form action="management/create.php?accion=profesional" method="post" class="mt-5 grid gap-4 sm:grid-cols-2">
                    <?php echo csrf_input(); ?>
                    <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Nombre completo
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="name_professional" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Usuario
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="user_professional" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Correo
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="email_professional" type="email" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Teléfono
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="phone_professional" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Color
                        <input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="calendar_color" type="color" value="#0f172a" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Disciplina
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="id_disciplina">
                            <option value="">General</option>
                            <?php foreach ($disciplinas as $disciplina): ?>
                                <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>"><?php echo escape_html($disciplina['nombre_disciplina']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Contraseña
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="pass_professional" type="password" minlength="8" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Bio
                        <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="bio_professional"></textarea>
                    </label>
                    <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit">
                        Crear profesional
                    </button>
                </form>
                <div class="mt-6 space-y-3">
                    <?php foreach (array_slice($profesionales, 0, 6) as $profesional): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="font-semibold"><?php echo escape_html($profesional['name_professional']); ?></div>
                                    <div class="mt-1 text-sm text-slate-500"><?php echo escape_html($profesional['nombre_disciplina'] ?: 'General'); ?></div>
                                    <div class="mt-2 text-xs text-slate-500"><?php echo escape_html(professional_schedule_summary($pdo, (int) $profesional['id_professional'])); ?></div>
                                </div>
                                <a class="text-sm font-medium text-slate-700 hover:text-slate-950" href="management/professional-edit.php?id_professional=<?php echo escape_html((string) $profesional['id_professional']); ?>">Editar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="clientes" class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Clientes</div>
                <h3 class="mt-2 text-xl font-semibold">Alta rápida y gestión</h3>
                <form action="management/create.php?accion=cliente" method="post" class="mt-5 grid gap-4 sm:grid-cols-2">
                    <?php echo csrf_input(); ?>
                    <label class="block text-sm font-medium text-slate-600">Nombre
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="nombre_cliente" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Apellido
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="apellido_cliente" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Teléfono
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="telefono_cliente" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Correo
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="correo_cliente" type="email" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Usuario opcional
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="user_cliente">
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Contraseña temporal
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="pass_cliente" type="text">
                    </label>
                    <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">
                        Crear cliente
                    </button>
                </form>
                <div class="mt-6 space-y-3">
                    <?php foreach (array_slice($clientes, 0, 6) as $cliente): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="font-semibold"><?php echo escape_html($cliente['nombre_cliente'] . ' ' . $cliente['apellido_cliente']); ?></div>
                                    <div class="mt-1 text-sm text-slate-500"><?php echo escape_html($cliente['correo_cliente']); ?></div>
                                </div>
                                <a class="text-sm font-medium text-slate-700 hover:text-slate-950" href="management/customer-edit.php?id_cliente=<?php echo escape_html((string) $cliente['id_cliente']); ?>">Editar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <section id="disciplinas" class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Disciplinas</div>
                <form action="management/create.php?accion=disciplina" method="post" class="mt-4 space-y-4">
                    <?php echo csrf_input(); ?>
                    <label class="block text-sm font-medium text-slate-600">Nombre
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="nombre_disciplina" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Descripción
                        <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="descripcion_disciplina"></textarea>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Color
                        <input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="color_disciplina" type="color" value="#0f766e" required>
                    </label>
                    <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="submit">
                        Crear disciplina
                    </button>
                </form>
            </section>

            <section id="servicios" class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Servicios</div>
                <form action="management/create.php?accion=servicio" method="post" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <?php echo csrf_input(); ?>
                    <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Nombre
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="nombre_servicio" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Disciplina
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="id_disciplina">
                            <option value="">General</option>
                            <?php foreach ($disciplinas as $disciplina): ?>
                                <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>"><?php echo escape_html($disciplina['nombre_disciplina']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Precio
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="precio_servicio" type="number" min="0" step="0.01" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Duración
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="duracion_minutos" type="number" min="15" step="15" value="60" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Modalidad
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="modalidad_servicio" value="Presencial" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Color fondo
                        <input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="color" type="color" value="#0f766e" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Color texto
                        <input class="mt-2 h-12 w-full rounded-2xl border border-slate-200 px-2 py-2" name="textColor" type="color" value="#ffffff" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Imagen
                        <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" name="img_servicio" type="file" accept=".jpg,.jpeg,.png,.webp" required>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Descripción
                        <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3" name="descripcion_servicio" required></textarea>
                    </label>
                    <button class="sm:col-span-2 inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">
                        Crear servicio
                    </button>
                </form>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <?php foreach (array_slice($servicios, 0, 6) as $servicio): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="font-semibold"><?php echo escape_html($servicio['nombre_servicio']); ?></div>
                                    <div class="mt-1 text-sm text-slate-500"><?php echo escape_html(($servicio['nombre_disciplina'] ?: 'General') . ' · ' . $servicio['duracion_minutos'] . ' min'); ?></div>
                                </div>
                                <a class="text-sm font-medium text-slate-700 hover:text-slate-950" href="management/service-edit.php?id_servicio=<?php echo escape_html((string) $servicio['id_servicio']); ?>">Editar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>
    </main>

    <dialog id="booking-modal" class="w-[min(94vw,48rem)] rounded-[2rem] border-0 p-0">
        <div class="rounded-[2rem] bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Detalle de reserva</div>
                    <h3 class="mt-2 text-2xl font-semibold" id="modal-title">Reserva</h3>
                </div>
                <button class="rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600" onclick="document.getElementById('booking-modal').close()" type="button">Cerrar</button>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 text-sm text-slate-600">
                <div><strong class="block text-slate-900">Cliente</strong><span id="modal-customer"></span></div>
                <div><strong class="block text-slate-900">Teléfono</strong><span id="modal-phone"></span></div>
                <div><strong class="block text-slate-900">Disciplina</strong><span id="modal-discipline"></span></div>
                <div><strong class="block text-slate-900">Servicio</strong><span id="modal-service"></span></div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-600">
                    Profesional
                    <select id="modal-professional-select" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3"></select>
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Estado
                    <select id="modal-status-select" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                        <option value="confirmada">Confirmada</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Inicio
                    <input id="modal-start-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Término
                    <input id="modal-end-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600 sm:col-span-2">
                    Notas
                    <textarea id="modal-notes-input" class="mt-2 min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3"></textarea>
                </label>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a id="modal-edit-link" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700" href="#">Abrir edición completa</a>
                <button id="modal-save-button" class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="button">Guardar cambios</button>
                <button id="modal-delete-button" class="inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-600" type="button">Eliminar reserva</button>
            </div>
        </div>
    </dialog>

    <script>
        const modal = document.getElementById('booking-modal');
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const professionalOptions = <?php echo json_encode(array_map(static function (array $profesional): array {
            return array(
                'id' => (int) $profesional['id_professional'],
                'name' => (string) $profesional['name_professional'],
            );
        }, $profesionales), JSON_UNESCAPED_UNICODE); ?>;
        const professionalSelect = document.getElementById('modal-professional-select');
        const statusSelect = document.getElementById('modal-status-select');
        const startInput = document.getElementById('modal-start-input');
        const endInput = document.getElementById('modal-end-input');
        const notesInput = document.getElementById('modal-notes-input');
        const editLink = document.getElementById('modal-edit-link');
        let selectedEvent = null;

        professionalOptions.forEach((professional) => {
            const option = document.createElement('option');
            option.value = String(professional.id);
            option.textContent = professional.name;
            professionalSelect.appendChild(option);
        });

        const toDateTimeLocal = (date) => {
            const current = new Date(date);
            current.setMinutes(current.getMinutes() - current.getTimezoneOffset());
            return current.toISOString().slice(0, 16);
        };

        const toApiDate = (value) => value ? new Date(value).toISOString() : '';

        const populateModal = (event) => {
            const props = event.extendedProps;
            selectedEvent = event;
            document.getElementById('modal-title').textContent = props.nombre_servicio || 'Reserva';
            document.getElementById('modal-customer').textContent = `${props.nombre_cliente || ''} ${props.apellido_cliente || ''}`.trim() || '-';
            document.getElementById('modal-phone').textContent = props.telefono_cliente || '-';
            document.getElementById('modal-discipline').textContent = props.nombre_disciplina || 'General';
            document.getElementById('modal-service').textContent = props.nombre_servicio || '-';
            professionalSelect.value = String(props.id_professional || event.extendedProps.id_professional || '');
            statusSelect.value = props.estado_reserva || 'confirmada';
            startInput.value = toDateTimeLocal(event.start);
            endInput.value = toDateTimeLocal(event.end);
            notesInput.value = props.notas_reserva || '';
            editLink.href = `management/booking-edit.php?id_evento=${event.id}`;
        };

        const syncEventLocally = (event, payload) => {
            if (payload.start) {
                event.setStart(payload.start);
            }
            if (payload.end) {
                event.setEnd(payload.end);
            }
            if (payload.id_professional) {
                const professional = professionalOptions.find((item) => String(item.id) === String(payload.id_professional));
                event.setExtendedProp('id_professional', Number(payload.id_professional));
                event.setExtendedProp('name_professional', professional ? professional.name : event.extendedProps.name_professional);
            }
            if (payload.estado_reserva) {
                event.setExtendedProp('estado_reserva', payload.estado_reserva);
            }
            if (typeof payload.notas_reserva === 'string') {
                event.setExtendedProp('notas_reserva', payload.notas_reserva);
            }
        };

        const persistAdminEvent = async (event, payload, revertCallback = null) => {
            const body = new URLSearchParams({
                id_evento: event.id,
                csrf_token: csrfToken,
                id_professional: String(payload.id_professional || event.extendedProps.id_professional || ''),
                estado_reserva: payload.estado_reserva || event.extendedProps.estado_reserva || 'confirmada',
                notas_reserva: typeof payload.notas_reserva === 'string' ? payload.notas_reserva : (event.extendedProps.notas_reserva || ''),
                start: payload.start || event.start.toISOString(),
                end: payload.end || event.end.toISOString()
            });

            const response = await fetch('bookings/api.php?accion=update_event_admin', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: body.toString()
            });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                if (revertCallback) {
                    revertCallback();
                }
                alert(result.error || 'No se pudo actualizar la reserva');
                return false;
            }

            syncEventLocally(event, payload);
            return true;
        };

        const calendar = new FullCalendar.Calendar(document.getElementById('admin-calendar'), {
            locale: 'es',
            initialView: window.innerWidth < 768 ? 'timeGridDay' : 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Lista'
            },
            height: 'auto',
            nowIndicator: true,
            editable: true,
            eventDurationEditable: true,
            slotMinTime: '<?php echo escape_html($hours['opening']); ?>:00',
            slotMaxTime: '<?php echo escape_html($hours['closing']); ?>:00',
            slotDuration: '00:30:00',
            events: async (_info, success, failure) => {
                try {
                    const response = await fetch('bookings/api.php');
                    const raw = await response.json();
                    success(raw.map((event) => ({
                        ...event,
                        id: String(event.id_evento),
                        title: `${event.nombre_servicio || 'Reserva'} · ${event.nombre_cliente || ''}`.trim(),
                        backgroundColor: event.color || event.calendar_color || '#0f766e',
                        textColor: event.textColor || '#ffffff'
                    })));
                } catch (error) {
                    failure(error);
                }
            },
            eventClick: ({ event }) => {
                populateModal(event);
                modal.showModal();
            },
            eventDrop: async ({ event, revert }) => {
                await persistAdminEvent(event, {
                    start: event.start.toISOString(),
                    end: event.end.toISOString()
                }, revert);
            },
            eventResize: async ({ event, revert }) => {
                await persistAdminEvent(event, {
                    start: event.start.toISOString(),
                    end: event.end.toISOString()
                }, revert);
            }
        });
        calendar.render();

        document.getElementById('modal-save-button').addEventListener('click', async () => {
            if (!selectedEvent) {
                return;
            }

            const updated = await persistAdminEvent(selectedEvent, {
                id_professional: professionalSelect.value,
                estado_reserva: statusSelect.value,
                notas_reserva: notesInput.value,
                start: toApiDate(startInput.value),
                end: toApiDate(endInput.value)
            });

            if (updated) {
                populateModal(selectedEvent);
                calendar.refetchEvents();
                modal.close();
            }
        });

        document.getElementById('modal-delete-button').addEventListener('click', async () => {
            if (!selectedEvent || !confirm('¿Eliminar esta reserva?')) {
                return;
            }

            const response = await fetch('bookings/api.php?accion=eliminar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    id_evento: selectedEvent.id,
                    csrf_token: csrfToken
                }).toString()
            });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                alert(result.error || 'No se pudo eliminar la reserva');
                return;
            }

            selectedEvent.remove();
            modal.close();
        });
    </script>
</body>
</html>
