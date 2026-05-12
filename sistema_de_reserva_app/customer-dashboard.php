<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_role('id_cliente', '3', 'index.php');

$pdo = app_pdo();
$theme = current_theme();
$csrfToken = csrf_token();
$disciplinas = fetch_all($pdo->prepare('SELECT * FROM disciplinas WHERE activa = 1 ORDER BY nombre_disciplina'));
$profesionales = fetch_all(
    $pdo->prepare(
        'SELECT professionals.*, disciplinas.nombre_disciplina
         FROM professionals
         LEFT JOIN disciplinas ON professionals.id_disciplina = disciplinas.id_disciplina
         WHERE professionals.activo = 1
         ORDER BY professionals.name_professional'
    )
);
$servicios = fetch_all(
    $pdo->prepare(
        'SELECT servicios.*, disciplinas.nombre_disciplina
         FROM servicios
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         WHERE servicios.activo = 1
         ORDER BY servicios.nombre_servicio'
    )
);
$reservas = fetch_all(
    $pdo->prepare(
        'SELECT eventos.*, professionals.name_professional, servicios.nombre_servicio, servicios.duracion_minutos, disciplinas.nombre_disciplina
         FROM eventos
         JOIN professionals ON eventos.id_professional = professionals.id_professional
         JOIN servicios ON eventos.id_servicio = servicios.id_servicio
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         WHERE eventos.id_cliente = :id_cliente
         ORDER BY eventos.start ASC'
    ),
    array(':id_cliente' => request_session_int('id_cliente'))
);
$hours = business_hours();
$ownedReservationIds = array_map(static fn (array $reserva): int => (int) $reserva['id_evento'], $reservas);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel cliente | <?php echo escape_html(app_brand_name()); ?></title>
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
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 14%, transparent), transparent 25%),
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
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Panel cliente</div>
                <h1 class="text-lg font-semibold"><?php echo escape_html($_SESSION['nombre_cliente'] . ' ' . $_SESSION['apellido_cliente']); ?></h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="customer/profile.php" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300">Mi perfil</a>
                <a href="logout.php" class="rounded-full px-4 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">Salir</a>
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1.45fr_0.8fr] lg:px-8">
        <section class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-2xl backdrop-blur-xl sm:p-6">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Agenda visual</div>
                    <h2 class="mt-2 text-2xl font-semibold">Reservas y disponibilidad</h2>
                </div>
                <div class="flex flex-wrap gap-2 text-sm text-slate-500">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Horario <?php echo escape_html($hours['opening']); ?> - <?php echo escape_html($hours['closing']); ?></span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Tus reservas se pueden editar desde el modal</span>
                </div>
            </div>
            <div class="mt-5" id="calendar"></div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Nueva reserva</div>
                <h3 class="mt-2 text-xl font-semibold">Reserva en pocos pasos</h3>
                <form id="client-booking-form" class="mt-5 space-y-4">
                    <label class="block text-sm font-medium text-slate-600">Disciplina
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="disciplina_cliente">
                            <option value="">Todas</option>
                            <?php foreach ($disciplinas as $disciplina): ?>
                                <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>"><?php echo escape_html($disciplina['nombre_disciplina']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Servicio
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="txt_servicio" required>
                            <option value="">Selecciona servicio</option>
                            <?php foreach ($servicios as $servicio): ?>
                                <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>" data-disciplina="<?php echo escape_html((string) ($servicio['id_disciplina'] ?? '')); ?>">
                                    <?php echo escape_html($servicio['nombre_servicio'] . ' · ' . $servicio['duracion_minutos'] . ' min'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Profesional
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="professional_id" required>
                            <option value="">Selecciona profesional</option>
                            <?php foreach ($profesionales as $profesional): ?>
                                <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>" data-disciplina="<?php echo escape_html((string) ($profesional['id_disciplina'] ?? '')); ?>">
                                    <?php echo escape_html($profesional['name_professional'] . ' · ' . ($profesional['nombre_disciplina'] ?: 'General')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-600">Fecha
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="dia" type="date" required>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Hora
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="hora_comienzo" type="time" min="<?php echo escape_html($hours['opening']); ?>" max="<?php echo escape_html($hours['closing']); ?>" step="<?php echo escape_html((string) ($hours['slot_interval'] * 60)); ?>" required>
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-slate-600">Notas
                        <textarea class="mt-2 min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="notas_reserva"></textarea>
                    </label>
                    <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" id="btn_reservar" type="button">
                        Reservar horario
                    </button>
                </form>
            </section>

            <section class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Próximas reservas</div>
                <div class="mt-4 space-y-3">
                    <?php foreach (array_slice($reservas, 0, 4) as $reserva): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="font-semibold"><?php echo escape_html($reserva['nombre_servicio']); ?></div>
                            <div class="mt-1 text-sm text-slate-500"><?php echo escape_html($reserva['name_professional']); ?></div>
                            <div class="mt-2 text-sm text-slate-600"><?php echo escape_html($reserva['start']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </main>

    <dialog id="booking-modal" class="w-[min(92vw,38rem)] rounded-[2rem] border-0 p-0">
        <div class="rounded-[2rem] bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Disponibilidad</div>
                    <h3 class="mt-2 text-2xl font-semibold" id="modal-title">Reserva</h3>
                </div>
                <button class="rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600" onclick="document.getElementById('booking-modal').close()" type="button">Cerrar</button>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 text-sm text-slate-600">
                <div><strong class="block text-slate-900">Profesional</strong><span id="modal-professional"></span></div>
                <div><strong class="block text-slate-900">Disciplina</strong><span id="modal-discipline"></span></div>
                <div><strong class="block text-slate-900">Horario</strong><span id="modal-schedule"></span></div>
                <div><strong class="block text-slate-900">Estado</strong><span id="modal-status"></span></div>
                <div><strong class="block text-slate-900">Servicio</strong><span id="modal-service"></span></div>
            </div>
            <div id="modal-owned-actions" class="mt-6 hidden flex-col gap-3 sm:flex-row">
                <a id="modal-edit-link" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700" href="#">Editar reserva</a>
                <form id="modal-delete-form" method="post" class="flex-1">
                    <?php echo csrf_input(); ?>
                    <button class="inline-flex w-full items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-600" type="submit">Cancelar reserva</button>
                </form>
            </div>
            <p id="modal-readonly-note" class="mt-6 text-sm text-slate-500">Este bloque corresponde a una reserva ocupada en la agenda.</p>
        </div>
    </dialog>

    <script>
        const modal = document.getElementById('booking-modal');
        const ownedReservationIds = new Set(<?php echo json_encode($ownedReservationIds); ?>.map((value) => Number(value)));
        const pad = (value) => String(value).padStart(2, '0');
        const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const formatDateTime = (date) => new Date(date).toLocaleString('es-CL', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
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
            selectable: true,
            slotMinTime: '<?php echo escape_html($hours['opening']); ?>:00',
            slotMaxTime: '<?php echo escape_html($hours['closing']); ?>:00',
            slotDuration: '00:30:00',
            events: async (_info, success, failure) => {
                try {
                    const response = await fetch('bookings/public-feed.php');
                    const raw = await response.json();
                    success(raw.map((event) => ({
                        ...event,
                        id: String(event.id_evento),
                        title: event.nombre_servicio || 'Reserva',
                        backgroundColor: event.color || event.calendar_color || '#0f766e',
                        textColor: event.textColor || '#ffffff'
                    })));
                } catch (error) {
                    failure(error);
                }
            },
            dateClick: (info) => {
                document.getElementById('dia').value = formatDate(info.date);
                document.getElementById('hora_comienzo').value = '<?php echo escape_html($hours['opening']); ?>';
            },
            eventClick: ({ event }) => {
                const props = event.extendedProps;
                const reservationId = Number(event.id);
                const isOwned = ownedReservationIds.has(reservationId);

                document.getElementById('modal-title').textContent = event.title;
                document.getElementById('modal-professional').textContent = props.name_professional || '-';
                document.getElementById('modal-discipline').textContent = props.nombre_disciplina || 'General';
                document.getElementById('modal-schedule').textContent = `${formatDateTime(event.start)} - ${formatDateTime(event.end)}`;
                document.getElementById('modal-status').textContent = props.estado_reserva || 'confirmada';
                document.getElementById('modal-service').textContent = props.nombre_servicio || '-';

                const actions = document.getElementById('modal-owned-actions');
                const note = document.getElementById('modal-readonly-note');
                actions.classList.toggle('hidden', !isOwned);
                note.textContent = isOwned
                    ? 'Puedes reagendar o cancelar esta reserva.'
                    : 'Este bloque corresponde a una reserva ocupada en la agenda.';

                if (isOwned) {
                    document.getElementById('modal-edit-link').href = `customer/booking-edit.php?id_evento=${reservationId}`;
                    document.getElementById('modal-delete-form').action = `customer/delete.php?id_evento=${reservationId}`;
                }

                modal.showModal();
            }
        });
        calendar.render();

        document.getElementById('disciplina_cliente').addEventListener('change', (event) => {
            const discipline = String(event.target.value);
            document.querySelectorAll('#txt_servicio option[data-disciplina], #professional_id option[data-disciplina]').forEach((option) => {
                option.hidden = discipline !== '' && String(option.dataset.disciplina) !== discipline;
            });
        });

        document.getElementById('btn_reservar').addEventListener('click', async () => {
            const payload = new URLSearchParams({
                professional_id: document.getElementById('professional_id').value,
                id_servicio: document.getElementById('txt_servicio').value,
                dia: document.getElementById('dia').value,
                hora: document.getElementById('hora_comienzo').value,
                notas_reserva: document.getElementById('notas_reserva').value,
                csrf_token: <?php echo json_encode($csrfToken); ?>
            });
            const response = await fetch('bookings/api.php?accion=agendar_customer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': <?php echo json_encode($csrfToken); ?>
                },
                body: payload.toString()
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                alert(result.error || 'No se pudo crear la reserva');
                return;
            }
            calendar.refetchEvents();
            document.getElementById('client-booking-form').reset();
        });
    </script>
</body>
</html>
