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
$waitlist = fetch_all(
    $pdo->prepare(
        'SELECT waitlist_requests.*, clientes.nombre_cliente, clientes.apellido_cliente, professionals.name_professional, servicios.nombre_servicio
         FROM waitlist_requests
         JOIN clientes ON waitlist_requests.id_cliente = clientes.id_cliente
         LEFT JOIN professionals ON waitlist_requests.id_professional = professionals.id_professional
         JOIN servicios ON waitlist_requests.id_servicio = servicios.id_servicio
         ORDER BY waitlist_requests.created_at DESC LIMIT 6'
    )
);
$auditEntries = fetch_all($pdo->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 6'));
$notifications = fetch_all($pdo->prepare('SELECT * FROM notification_log ORDER BY created_at DESC LIMIT 6'));
$hours = business_hours();
$brandLogo = trim((string) ($settings['brand_logo'] ?? ''));
$brandFavicon = trim((string) ($settings['brand_favicon'] ?? ''));
$statusSummary = array_fill_keys(array_keys(reservation_status_map()), 0);
$upcomingReservations = 0;
$cancelledReservations = 0;
$serviceDemand = array();
$professionalDemand = array();

foreach ($reservas as $reserva) {
    $status = (string) ($reserva['estado_reserva'] ?? 'confirmada');
    if (isset($statusSummary[$status])) {
        $statusSummary[$status]++;
    }
    if ($status === 'cancelada') {
        $cancelledReservations++;
    }
    if ($status !== 'cancelada' && new DateTimeImmutable((string) $reserva['start']) >= new DateTimeImmutable('today')) {
        $upcomingReservations++;
    }
    $serviceName = (string) $reserva['nombre_servicio'];
    $professionalName = (string) $reserva['name_professional'];
    $serviceDemand[$serviceName] = ($serviceDemand[$serviceName] ?? 0) + 1;
    $professionalDemand[$professionalName] = ($professionalDemand[$professionalName] ?? 0) + 1;
}

arsort($serviceDemand);
arsort($professionalDemand);
$topServiceName = (string) (array_key_first($serviceDemand) ?? 'Sin datos');
$topServiceCount = (int) ($serviceDemand[$topServiceName] ?? 0);
$topProfessionalName = (string) (array_key_first($professionalDemand) ?? 'Sin datos');
$topProfessionalCount = (int) ($professionalDemand[$topProfessionalName] ?? 0);
$notificationQueued = count(array_filter($notifications, static fn (array $row): bool => (string) $row['status'] === 'queued'));
$completedReservations = (int) ($statusSummary['completada'] ?? 0);
$pendingReservations = (int) (($statusSummary['pendiente'] ?? 0) + ($statusSummary['confirmada'] ?? 0) + ($statusSummary['en_progreso'] ?? 0));
$waitlistCount = count($waitlist);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administrador | <?php echo escape_html(app_brand_name()); ?></title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => $brandFavicon,
            'fullcalendar' => true,
            'body_background' => 'radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 16%, transparent), transparent 28%), radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 12%, transparent), transparent 22%), linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%)',
        )
    ); ?>
    <style>
        .fc .fc-toolbar-title { font-size: 1.05rem; font-weight: 750; color: #0f172a; }
        .fc .fc-button { border: 0; border-radius: 999px; background: #e2e8f0; color: #0f172a; padding: 0.65rem 0.95rem; box-shadow: none; }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active { background: var(--secondary); color: #fff; }
        .fc .fc-scrollgrid, .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(148, 163, 184, 0.22); }
        .fc-event { border: 0; border-radius: 16px; padding: 4px 6px; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10); }
        .premium-shell { max-width: 1500px; }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <header class="sticky top-0 z-40 border-b border-white/60 bg-white/75 backdrop-blur-2xl">
        <div class="app-shell premium-shell flex flex-col gap-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <?php if ($brandLogo !== ''): ?>
                    <img src="assets/branding/<?php echo escape_html($brandLogo); ?>" alt="Logo" class="h-11 w-11 rounded-2xl object-cover shadow-sm">
                <?php else: ?>
                    <div class="h-11 w-11 rounded-2xl bg-slate-950 shadow-sm"></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Command center</div>
                    <h1 class="truncate text-lg font-semibold text-slate-950"><?php echo escape_html(app_display_name()); ?></h1>
                </div>
            </div>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="#overview" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300 hover:text-slate-950">Dashboard</a>
                <a href="#agenda" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300 hover:text-slate-950">Calendario</a>
                <a href="#modulos" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300 hover:text-slate-950">Operacion</a>
                <a href="admin-settings.php" class="rounded-full border border-slate-950 bg-slate-950 px-4 py-2 text-white transition hover:bg-slate-800">Configuracion</a>
                <a href="logout.php" class="rounded-full px-4 py-2 font-semibold text-white" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">Salir</a>
            </nav>
        </div>
    </header>

    <main class="app-shell premium-shell space-y-6 py-6">
        <section id="overview" class="surface-card surface-card-body overflow-hidden">
            <div class="flex flex-col gap-5 border-b border-slate-200 pb-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Resumen operativo</div>
                    <h2 class="mt-2 max-w-4xl text-3xl font-semibold tracking-tight text-slate-950">Agenda, demanda y operacion diaria en una vista clara.</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">La configuracion sale del dashboard para reducir ruido. Esta pantalla queda enfocada en reservas, calendario y acciones frecuentes.</p>
                </div>
                <div class="grid gap-2 sm:grid-cols-3">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600"><?php echo $pendingReservations; ?> activas</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600"><?php echo $upcomingReservations; ?> futuras</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600"><?php echo $waitlistCount; ?> espera</span>
                </div>
            </div>
            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-[1.5rem] border border-white/70 bg-white/85 p-5 shadow-lg backdrop-blur-xl">
                    <div class="text-sm text-slate-500">Reservas totales</div>
                    <div class="mt-3 text-4xl font-semibold text-slate-950"><?php echo count($reservas); ?></div>
                    <div class="mt-2 text-sm text-slate-500"><?php echo $upcomingReservations; ?> proximas</div>
                </article>
                <article class="rounded-[1.5rem] border border-white/70 bg-white/85 p-5 shadow-lg backdrop-blur-xl">
                    <div class="text-sm text-slate-500">Pendientes</div>
                    <div class="mt-3 text-4xl font-semibold text-slate-950"><?php echo $pendingReservations; ?></div>
                    <div class="mt-2 text-sm text-slate-500"><?php echo $completedReservations; ?> completadas</div>
                </article>
                <article class="rounded-[1.5rem] border border-white/70 bg-white/85 p-5 shadow-lg backdrop-blur-xl">
                    <div class="text-sm text-slate-500">Profesional top</div>
                    <div class="mt-3 break-words text-2xl font-semibold text-slate-950"><?php echo escape_html($topProfessionalName); ?></div>
                    <div class="mt-2 text-sm text-slate-500"><?php echo $topProfessionalCount; ?> reservas</div>
                </article>
                <article class="rounded-[1.5rem] border border-white/70 bg-white/85 p-5 shadow-lg backdrop-blur-xl">
                    <div class="text-sm text-slate-500">Servicio top</div>
                    <div class="mt-3 break-words text-2xl font-semibold text-slate-950"><?php echo escape_html($topServiceName); ?></div>
                    <div class="mt-2 text-sm text-slate-500"><?php echo $topServiceCount; ?> reservas</div>
                </article>
                <article class="rounded-[1.5rem] border border-white/70 bg-white/85 p-5 shadow-lg backdrop-blur-xl">
                    <div class="text-sm text-slate-500">Alertas</div>
                    <div class="mt-3 text-4xl font-semibold text-slate-950"><?php echo $notificationQueued; ?></div>
                    <div class="mt-2 text-sm text-slate-500"><?php echo $cancelledReservations; ?> canceladas</div>
                </article>
            </div>
        </section>

        <section id="agenda" class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(22rem,0.75fr)]">
            <div class="surface-card surface-card-body min-w-0">
                <div class="mb-5 flex flex-col gap-4 border-b border-slate-200 pb-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Calendario global</div>
                            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Agenda editable</h2>
                        </div>
                        <p class="max-w-xl text-sm leading-6 text-slate-500">Arrastra, cambia duracion y filtra por profesional, estado o servicio. Los bloqueos del profesional aparecen como overlay visual.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="block text-sm font-medium text-slate-600">Profesional
                            <select id="calendar-professional-filter" class="field-input mt-2">
                                <option value="">Todos</option>
                                <?php foreach ($profesionales as $profesional): ?>
                                    <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>"><?php echo escape_html($profesional['name_professional']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Estado
                            <select id="calendar-status-filter" class="field-input mt-2">
                                <option value="">Todos</option>
                                <?php foreach (reservation_status_map() as $statusKey => $statusMeta): ?>
                                    <option value="<?php echo escape_html($statusKey); ?>"><?php echo escape_html($statusMeta['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Servicio
                            <select id="calendar-service-filter" class="field-input mt-2">
                                <option value="">Todos</option>
                                <?php foreach ($servicios as $servicio): ?>
                                    <option value="<?php echo escape_html($servicio['nombre_servicio']); ?>"><?php echo escape_html($servicio['nombre_servicio']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                <div id="admin-calendar"></div>
            </div>

            <aside class="space-y-6 min-w-0">
                <section id="reservas" class="surface-card surface-card-body">
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Reserva rapida</div>
                    <h3 class="mt-2 text-xl font-semibold text-slate-950">Crear nueva reserva</h3>
                    <form action="management/create.php?accion=reserva" method="post" class="mt-5 grid gap-4">
                        <?php echo csrf_input(); ?>
                        <label class="block text-sm font-medium text-slate-600">Cliente
                            <select class="field-input mt-2" name="id_cliente" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo escape_html((string) $cliente['id_cliente']); ?>"><?php echo escape_html($cliente['nombre_cliente'] . ' ' . $cliente['apellido_cliente']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Profesional
                            <select class="field-input mt-2" name="id_professional" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($profesionales as $profesional): ?>
                                    <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>"><?php echo escape_html($profesional['name_professional']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Servicio
                            <select class="field-input mt-2" name="id_servicio" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($servicios as $servicio): ?>
                                    <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>"><?php echo escape_html($servicio['nombre_servicio']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-slate-600">Inicio<input class="field-input mt-2" name="start" type="datetime-local" required></label>
                            <label class="block text-sm font-medium text-slate-600">Termino<input class="field-input mt-2" name="end" type="datetime-local" required></label>
                        </div>
                        <label class="block text-sm font-medium text-slate-600">Notas<textarea class="field-textarea mt-2 min-h-24" name="notas_reserva"></textarea></label>
                        <button class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">Crear reserva</button>
                    </form>
                </section>

                <section class="surface-card surface-card-body">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Actividad</div>
                            <h3 class="mt-2 text-xl font-semibold text-slate-950">Cola operativa</h3>
                        </div>
                        <a class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-950" href="admin-settings.php#operacion">Operacion</a>
                    </div>
                    <div class="mt-5 space-y-3">
                        <?php if ($waitlist === array()): ?>
                            <?php render_empty_state('Sin lista de espera', 'Cuando falte capacidad, las solicitudes apareceran aqui.'); ?>
                        <?php else: ?>
                            <?php foreach ($waitlist as $item): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                    <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $item['nombre_cliente'] . ' ' . (string) $item['apellido_cliente']); ?></div>
                                    <div class="mt-1 break-words"><?php echo escape_html((string) $item['nombre_servicio']); ?> · <?php echo escape_html((string) ($item['name_professional'] ?: 'Cualquier profesional')); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </section>

        <section id="modulos" class="grid gap-6 xl:grid-cols-3">
            <article class="surface-card surface-card-body min-w-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Staff</div>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950">Profesionales</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600"><?php echo count($profesionales); ?></span>
                </div>
                <form action="management/create.php?accion=profesional" method="post" class="mt-5 grid gap-3">
                    <?php echo csrf_input(); ?>
                    <input class="field-input" name="name_professional" placeholder="Nombre profesional" required>
                    <select class="field-input" name="id_disciplina">
                        <option value="">Disciplina general</option>
                        <?php foreach ($disciplinas as $disciplina): ?>
                            <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>"><?php echo escape_html($disciplina['nombre_disciplina']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input class="field-input" name="telefono_professional" placeholder="Telefono">
                        <input class="field-input" name="email_professional" type="email" placeholder="Email">
                    </div>
                    <button class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Agregar profesional</button>
                </form>
                <input id="professional-search" class="field-input mt-5" placeholder="Buscar profesional">
                <div class="mt-4 max-h-[30rem] space-y-3 overflow-y-auto pr-1" data-filter-list="professionals">
                    <?php if ($profesionales === array()): ?>
                        <?php render_empty_state('Sin profesionales', 'Agrega el primer integrante para abrir agenda.'); ?>
                    <?php else: ?>
                        <?php foreach ($profesionales as $profesional): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-filter-item="<?php echo escape_html(strtolower($profesional['name_professional'] . ' ' . ($profesional['nombre_disciplina'] ?: ''))); ?>">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="break-words font-semibold text-slate-950"><?php echo escape_html($profesional['name_professional']); ?></div>
                                        <div class="mt-1 break-words text-sm text-slate-500"><?php echo escape_html($profesional['nombre_disciplina'] ?: 'General'); ?></div>
                                    </div>
                                    <div class="flex shrink-0 flex-col gap-2 text-right text-sm">
                                        <a class="font-medium text-slate-700 hover:text-slate-950" href="management/professional-edit.php?id_professional=<?php echo escape_html((string) $profesional['id_professional']); ?>">Editar</a>
                                        <form action="management/delete.php?accion=profesional&id_professional=<?php echo escape_html((string) $profesional['id_professional']); ?>" method="post"><?php echo csrf_input(); ?><button class="font-medium text-rose-600" type="submit">Eliminar</button></form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <article class="surface-card surface-card-body min-w-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Clientes</div>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950">Base de clientes</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600"><?php echo count($clientes); ?></span>
                </div>
                <form action="management/create.php?accion=cliente" method="post" class="mt-5 grid gap-3">
                    <?php echo csrf_input(); ?>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input class="field-input" name="nombre_cliente" placeholder="Nombre" required>
                        <input class="field-input" name="apellido_cliente" placeholder="Apellido" required>
                    </div>
                    <input class="field-input" name="telefono_cliente" placeholder="Telefono" required>
                    <input class="field-input" name="email_cliente" type="email" placeholder="Email">
                    <button class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Agregar cliente</button>
                </form>
                <input id="customer-search" class="field-input mt-5" placeholder="Buscar cliente">
                <div class="mt-4 max-h-[30rem] space-y-3 overflow-y-auto pr-1" data-filter-list="customers">
                    <?php if ($clientes === array()): ?>
                        <?php render_empty_state('Sin clientes', 'Crea clientes en segundos y agenda desde el calendario.'); ?>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-filter-item="<?php echo escape_html(strtolower($cliente['nombre_cliente'] . ' ' . $cliente['apellido_cliente'] . ' ' . $cliente['telefono_cliente'])); ?>">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="break-words font-semibold text-slate-950"><?php echo escape_html($cliente['nombre_cliente'] . ' ' . $cliente['apellido_cliente']); ?></div>
                                        <div class="mt-1 break-words text-sm text-slate-500"><?php echo escape_html($cliente['telefono_cliente']); ?></div>
                                    </div>
                                    <div class="flex shrink-0 flex-col gap-2 text-right text-sm">
                                        <a class="font-medium text-slate-700 hover:text-slate-950" href="management/customer-edit.php?id_cliente=<?php echo escape_html((string) $cliente['id_cliente']); ?>">Editar</a>
                                        <form action="management/delete.php?accion=cliente&id_cliente=<?php echo escape_html((string) $cliente['id_cliente']); ?>" method="post"><?php echo csrf_input(); ?><button class="font-medium text-rose-600" type="submit">Eliminar</button></form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <article class="surface-card surface-card-body min-w-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Catalogo</div>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950">Servicios</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600"><?php echo count($servicios); ?></span>
                </div>
                <form action="management/create.php?accion=servicio" method="post" enctype="multipart/form-data" class="mt-5 grid gap-3">
                    <?php echo csrf_input(); ?>
                    <input class="field-input" name="nombre_servicio" placeholder="Nombre del servicio" required>
                    <select class="field-input" name="id_disciplina">
                        <option value="">Disciplina general</option>
                        <?php foreach ($disciplinas as $disciplina): ?>
                            <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>"><?php echo escape_html($disciplina['nombre_disciplina']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input class="field-input" name="precio_servicio" type="number" min="0" step="0.01" placeholder="Precio" required>
                        <input class="field-input" name="duracion_minutos" type="number" min="5" step="5" placeholder="Duracion min" required>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input class="field-input" name="buffer_before_min" type="number" min="0" step="5" value="0" aria-label="Buffer antes">
                        <input class="field-input" name="buffer_after_min" type="number" min="0" step="5" value="0" aria-label="Buffer despues">
                    </div>
                    <select class="field-input" name="allows_parallel">
                        <option value="0">Exclusivo</option>
                        <option value="1">Compatible con agenda paralela</option>
                    </select>
                    <input class="field-input" name="img_servicio" type="file" accept=".jpg,.jpeg,.png,.webp" required aria-label="Imagen del servicio">
                    <textarea class="field-textarea min-h-24" name="descripcion_servicio" placeholder="Descripcion" required></textarea>
                    <button class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Crear servicio</button>
                </form>
                <input id="service-search" class="field-input mt-5" placeholder="Buscar servicio">
                <div class="mt-4 max-h-[30rem] space-y-3 overflow-y-auto pr-1" data-filter-list="services">
                    <?php if ($servicios === array()): ?>
                        <?php render_empty_state('Sin servicios', 'Crea servicios para habilitar reservas.'); ?>
                    <?php else: ?>
                        <?php foreach ($servicios as $servicio): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-filter-item="<?php echo escape_html(strtolower($servicio['nombre_servicio'] . ' ' . ($servicio['nombre_disciplina'] ?: ''))); ?>">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="break-words font-semibold text-slate-950"><?php echo escape_html($servicio['nombre_servicio']); ?></div>
                                        <div class="mt-1 break-words text-sm text-slate-500"><?php echo escape_html(($servicio['nombre_disciplina'] ?: 'General') . ' · ' . $servicio['duracion_minutos'] . ' min'); ?></div>
                                    </div>
                                    <div class="flex shrink-0 flex-col gap-2 text-right text-sm">
                                        <a class="font-medium text-slate-700 hover:text-slate-950" href="management/service-edit.php?id_servicio=<?php echo escape_html((string) $servicio['id_servicio']); ?>">Editar</a>
                                        <form action="management/delete.php?accion=servicio&id_servicio=<?php echo escape_html((string) $servicio['id_servicio']); ?>" method="post"><?php echo csrf_input(); ?><button class="font-medium text-rose-600" type="submit">Eliminar</button></form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        </section>

        <section class="surface-card surface-card-body">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Trazabilidad</div>
                    <h3 class="mt-2 text-xl font-semibold text-slate-950">Actividad reciente</h3>
                </div>
                <a class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-950" href="admin-settings.php#notificaciones">Ver notificaciones</a>
            </div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="space-y-3">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $notification['template_key']); ?> · <?php echo escape_html((string) $notification['channel']); ?></div>
                            <div class="mt-1 break-words"><?php echo escape_html((string) $notification['recipient']); ?></div>
                            <div class="mt-1"><?php echo escape_html((string) $notification['status']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="space-y-3">
                    <?php foreach ($auditEntries as $entry): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $entry['summary']); ?></div>
                            <div class="mt-1"><?php echo escape_html((string) $entry['entity_type']); ?> · <?php echo escape_html((string) $entry['action']); ?></div>
                            <div class="mt-1"><?php echo escape_html((string) $entry['created_at']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <dialog id="booking-modal" class="dialog-shell">
        <div class="dialog-body">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Detalle de reserva</div>
                    <h3 class="mt-2 text-2xl font-semibold" id="modal-title">Reserva</h3>
                </div>
                <button class="rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600" onclick="document.getElementById('booking-modal').close()" type="button">Cerrar</button>
            </div>
            <div class="mt-6 grid gap-4 text-sm text-slate-600 sm:grid-cols-2">
                <div><strong class="block text-slate-900">Cliente</strong><span id="modal-customer"></span></div>
                <div><strong class="block text-slate-900">Telefono</strong><span id="modal-phone"></span></div>
                <div><strong class="block text-slate-900">Disciplina</strong><span id="modal-discipline"></span></div>
                <div><strong class="block text-slate-900">Servicio</strong><span id="modal-service"></span></div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-600">Profesional<select id="modal-professional-select" class="field-input mt-2"><?php foreach ($profesionales as $profesional): ?><option value="<?php echo escape_html((string) $profesional['id_professional']); ?>"><?php echo escape_html($profesional['name_professional']); ?></option><?php endforeach; ?></select></label>
                <label class="block text-sm font-medium text-slate-600">Estado<select id="modal-status-select" class="field-input mt-2"><?php foreach (reservation_status_map() as $statusKey => $statusMeta): ?><option value="<?php echo escape_html($statusKey); ?>"><?php echo escape_html($statusMeta['label']); ?></option><?php endforeach; ?></select></label>
                <label class="block text-sm font-medium text-slate-600">Inicio<input id="modal-start-input" class="field-input mt-2" type="datetime-local"></label>
                <label class="block text-sm font-medium text-slate-600">Termino<input id="modal-end-input" class="field-input mt-2" type="datetime-local"></label>
                <label class="block text-sm font-medium text-slate-600 sm:col-span-2">Notas<textarea id="modal-notes-input" class="field-textarea mt-2 min-h-28"></textarea></label>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a id="modal-edit-link" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700" href="#">Abrir edicion completa</a>
                <button id="modal-save-button" class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="button">Guardar cambios</button>
                <button id="modal-delete-button" class="inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-600" type="button">Cancelar reserva</button>
            </div>
        </div>
    </dialog>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const professionalFilter = document.getElementById('calendar-professional-filter');
        const statusFilter = document.getElementById('calendar-status-filter');
        const serviceFilter = document.getElementById('calendar-service-filter');
        const modal = document.getElementById('booking-modal');
        let selectedEvent = null;

        const toLocalInputValue = (date) => {
            const localDate = new Date(date);
            localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
            return localDate.toISOString().slice(0, 16);
        };

        const availabilitySource = {
            id: 'availability',
            events: async (info, success, failure) => {
                if (!professionalFilter.value) {
                    success([]);
                    return;
                }
                try {
                    const response = await fetch(`bookings/availability-feed.php?professional_id=${encodeURIComponent(professionalFilter.value)}&start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`);
                    success(await response.json());
                } catch (error) {
                    failure(error);
                }
            }
        };

        const applyCalendarFilters = () => {
            calendar.getEvents().forEach((event) => {
                const props = event.extendedProps;
                const isAvailability = ['day_off', 'closed', 'break'].includes(props.kind || '');
                const professionalMatch = professionalFilter.value === '' || String(props.id_professional || '') === professionalFilter.value;
                const statusMatch = statusFilter.value === '' || props.estado_reserva === statusFilter.value;
                const serviceMatch = serviceFilter.value === '' || props.nombre_servicio === serviceFilter.value;
                const visible = isAvailability ? professionalFilter.value !== '' : professionalMatch && statusMatch && serviceMatch;
                event.setProp('display', visible ? (isAvailability ? 'background' : 'auto') : 'none');
            });
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
                day: 'Dia',
                list: 'Lista'
            },
            height: 'auto',
            nowIndicator: true,
            editable: true,
            eventDurationEditable: true,
            slotMinTime: '<?php echo escape_html($hours['opening']); ?>:00',
            slotMaxTime: '<?php echo escape_html($hours['closing']); ?>:00',
            slotDuration: '00:30:00',
            eventSources: [
                availabilitySource,
                {
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
                    }
                }
            ],
            loading: () => setTimeout(applyCalendarFilters, 0),
            eventClick: ({ event }) => {
                if (['day_off', 'closed', 'break'].includes(event.extendedProps.kind || '')) {
                    return;
                }
                selectedEvent = event;
                const props = event.extendedProps;
                document.getElementById('modal-title').textContent = props.nombre_servicio || 'Reserva';
                document.getElementById('modal-customer').textContent = `${props.nombre_cliente || ''} ${props.apellido_cliente || ''}`.trim() || '-';
                document.getElementById('modal-phone').textContent = props.telefono_cliente || '-';
                document.getElementById('modal-discipline').textContent = props.nombre_disciplina || 'General';
                document.getElementById('modal-service').textContent = props.nombre_servicio || '-';
                document.getElementById('modal-professional-select').value = String(props.id_professional || '');
                document.getElementById('modal-status-select').value = props.estado_reserva || 'confirmada';
                document.getElementById('modal-start-input').value = toLocalInputValue(event.start);
                document.getElementById('modal-end-input').value = toLocalInputValue(event.end);
                document.getElementById('modal-notes-input').value = props.notas_reserva || '';
                document.getElementById('modal-edit-link').href = `management/booking-edit.php?id_evento=${event.id}`;
                modal.showModal();
            },
            eventDrop: async ({ event, revert }) => {
                try {
                    await persistAdminEvent(event, { start: event.start.toISOString(), end: event.end.toISOString() });
                } catch (error) {
                    alert(error.message || 'No se pudo mover la reserva');
                    revert();
                }
            },
            eventResize: async ({ event, revert }) => {
                try {
                    await persistAdminEvent(event, { start: event.start.toISOString(), end: event.end.toISOString() });
                } catch (error) {
                    alert(error.message || 'No se pudo ajustar la reserva');
                    revert();
                }
            }
        });
        calendar.render();

        async function persistAdminEvent(event, payload) {
            const response = await fetch('bookings/api.php?accion=update_event_admin', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    id_evento: event.id,
                    id_professional: String(payload.id_professional || event.extendedProps.id_professional || ''),
                    estado_reserva: payload.estado_reserva || event.extendedProps.estado_reserva || 'confirmada',
                    notas_reserva: payload.notas_reserva || event.extendedProps.notas_reserva || '',
                    start: payload.start || event.start.toISOString(),
                    end: payload.end || event.end.toISOString(),
                    csrf_token: csrfToken
                }).toString()
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'No se pudo actualizar la reserva');
            }
        }

        [professionalFilter, statusFilter, serviceFilter].forEach((filter) => {
            filter.addEventListener('change', () => {
                if (filter === professionalFilter) {
                    calendar.getEventSourceById('availability')?.refetch();
                }
                applyCalendarFilters();
            });
        });

        document.getElementById('modal-save-button').addEventListener('click', async () => {
            if (!selectedEvent) {
                return;
            }
            try {
                await persistAdminEvent(selectedEvent, {
                    id_professional: document.getElementById('modal-professional-select').value,
                    estado_reserva: document.getElementById('modal-status-select').value,
                    notas_reserva: document.getElementById('modal-notes-input').value,
                    start: new Date(document.getElementById('modal-start-input').value).toISOString(),
                    end: new Date(document.getElementById('modal-end-input').value).toISOString()
                });
                modal.close();
                calendar.refetchEvents();
            } catch (error) {
                alert(error.message || 'No se pudo guardar la reserva');
            }
        });

        document.getElementById('modal-delete-button').addEventListener('click', async () => {
            if (!selectedEvent || !confirm('Eliminar esta reserva?')) {
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
            modal.close();
            calendar.refetchEvents();
        });

        const bindTextFilter = (inputId, listSelector) => {
            const input = document.getElementById(inputId);
            const items = Array.from(document.querySelectorAll(`${listSelector} [data-filter-item]`));
            if (!input) {
                return;
            }
            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                items.forEach((item) => {
                    item.classList.toggle('hidden', term !== '' && !item.dataset.filterItem.includes(term));
                });
            });
        };

        bindTextFilter('professional-search', '[data-filter-list="professionals"]');
        bindTextFilter('customer-search', '[data-filter-list="customers"]');
        bindTextFilter('service-search', '[data-filter-list="services"]');
    </script>
</body>
</html>
