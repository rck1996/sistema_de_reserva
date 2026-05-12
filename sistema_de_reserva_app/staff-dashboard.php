<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_role('id_professional', '2', 'staff-login.php');

$pdo = app_pdo();
$theme = current_theme();
$settings = app_settings($pdo);
$csrfToken = csrf_token();
$professionalId = request_session_int('id_professional');
$clientes = fetch_all($pdo->prepare('SELECT id_cliente, nombre_cliente, apellido_cliente FROM clientes ORDER BY nombre_cliente, apellido_cliente'));
$servicios = fetch_all(
    $pdo->prepare(
        'SELECT servicios.*, disciplinas.nombre_disciplina
         FROM servicios
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         WHERE servicios.activo = 1
         ORDER BY servicios.nombre_servicio'
    )
);
$hours = business_hours();
$scheduleSummary = professional_schedule_summary($pdo, $professionalId);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel profesional | <?php echo escape_html(app_brand_name()); ?></title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => $settings['brand_favicon'] ?? '',
            'fullcalendar' => true,
            'body_background' => 'radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 14%, transparent), transparent 24%), linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%)',
        )
    ); ?>
    <style>
        .fc .fc-toolbar-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .fc .fc-button {
            border-radius: 999px;
            border: 0;
            box-shadow: none;
            padding: 0.7rem 1rem;
            background: #e2e8f0;
            color: #0f172a;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background: var(--secondary);
        }
        .fc .fc-scrollgrid,
        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: rgba(148, 163, 184, 0.22);
        }
        .fc-event {
            border: 0;
            border-radius: 16px;
            padding: 4px 6px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10);
        }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <header class="sticky top-0 z-40 border-b border-white/50 bg-white/75 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Agenda profesional</div>
                <h1 class="text-lg font-semibold"><?php echo escape_html((string) ($_SESSION['name_professional'] ?? 'Profesional')); ?></h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="staff/profile.php" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300">Mi perfil</a>
                <a href="logout.php" class="rounded-full px-4 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, var(--accent), var(--primary));">Salir</a>
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1.45fr_0.8fr] lg:px-8">
        <section class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-2xl backdrop-blur-xl sm:p-6">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Calendario</div>
                        <h2 class="mt-2 text-2xl font-semibold">Disponibilidad y reservas en una sola vista</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm text-slate-500">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Horario <?php echo escape_html($hours['opening']); ?> - <?php echo escape_html($hours['closing']); ?></span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"><?php echo escape_html((string) $hours['slot_interval']); ?> min</span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"><?php echo escape_html($scheduleSummary); ?></span>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="block text-sm font-medium text-slate-600">
                        Estado
                        <select id="status-filter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                            <option value="">Todos</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">
                        Servicio
                        <select id="service-filter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                            <option value="">Todos</option>
                            <?php foreach ($servicios as $servicio): ?>
                                <option value="<?php echo escape_html($servicio['nombre_servicio']); ?>"><?php echo escape_html($servicio['nombre_servicio']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">
                        Vista
                        <select id="display-filter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                            <option value="all">Todo</option>
                            <option value="reservations">Solo reservas</option>
                            <option value="availability">Solo disponibilidad</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="mt-5" id="calendar"></div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Nueva reserva</div>
                <h3 class="mt-2 text-xl font-semibold">Agendamiento rápido</h3>
                <form id="pro-booking-form" class="mt-5 space-y-4">
                    <label class="block text-sm font-medium text-slate-600">Cliente
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="txt_cliente" required>
                            <option value="">Selecciona cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo escape_html((string) $cliente['id_cliente']); ?>"><?php echo escape_html($cliente['nombre_cliente'] . ' ' . $cliente['apellido_cliente']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Servicio
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-slate-400" id="txt_servicio" required>
                            <option value="">Selecciona servicio</option>
                            <?php foreach ($servicios as $servicio): ?>
                                <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>"><?php echo escape_html($servicio['nombre_servicio'] . ' · ' . $servicio['duracion_minutos'] . ' min'); ?></option>
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
                    <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95" style="background: linear-gradient(135deg, var(--accent), var(--primary));" id="btn_reservar" type="button">
                        Crear reserva
                    </button>
                </form>
            </section>

            <section class="rounded-[2rem] border border-white/50 bg-white/85 p-5 shadow-xl backdrop-blur-xl">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Lectura visual</div>
                <div class="mt-4 space-y-3 text-sm leading-7 text-slate-600">
                    <p><span class="inline-block h-3 w-3 rounded-full bg-slate-300"></span> Bloques grises: tiempo no laborable.</p>
                    <p><span class="inline-block h-3 w-3 rounded-full bg-orange-200"></span> Bloques naranjos: pausas del profesional.</p>
                    <p>Usa el modal para actualizar, reprogramar o eliminar reservas sin salir del calendario.</p>
                </div>
            </section>
        </aside>
    </main>

    <dialog id="booking-modal" class="w-[min(92vw,42rem)] rounded-[2rem] border-0 p-0">
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
                <div><strong class="block text-slate-900">Disciplina</strong><span id="modal-discipline"></span></div>
                <div><strong class="block text-slate-900">Teléfono</strong><span id="modal-phone"></span></div>
                <div><strong class="block text-slate-900">Servicio</strong><span id="modal-service"></span></div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-600">
                    Inicio
                    <input id="modal-start-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Término
                    <input id="modal-end-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Estado
                    <select id="modal-status-select" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                        <option value="confirmada">Confirmada</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-slate-600 sm:col-span-2">
                    Editar notas
                    <textarea id="modal-notes-input" class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3"></textarea>
                </label>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button id="modal-save-button" class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="button">Guardar cambios</button>
                <button id="modal-delete-button" class="inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-6 py-3 text-sm font-semibold text-rose-600" type="button">Eliminar reserva</button>
            </div>
        </div>
    </dialog>

    <script>
        const modal = document.getElementById('booking-modal');
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const stateFilter = document.getElementById('status-filter');
        const serviceFilter = document.getElementById('service-filter');
        const displayFilter = document.getElementById('display-filter');
        let selectedEvent = null;

        const pad = (value) => String(value).padStart(2, '0');
        const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const formatTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
        const toLocalInputValue = (date) => {
            const localDate = new Date(date);
            localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
            return localDate.toISOString().slice(0, 16);
        };

        const applyFilters = () => {
            calendar.getEvents().forEach((event) => {
                const props = event.extendedProps;
                const isAvailability = ['day_off', 'closed', 'break'].includes(props.kind || '');
                const stateMatch = stateFilter.value === '' || props.estado_reserva === stateFilter.value;
                const serviceMatch = serviceFilter.value === '' || props.nombre_servicio === serviceFilter.value;
                const displayMatch =
                    displayFilter.value === 'all' ||
                    (displayFilter.value === 'reservations' && !isAvailability) ||
                    (displayFilter.value === 'availability' && isAvailability);

                event.setProp('display', displayMatch && (isAvailability || (stateMatch && serviceMatch))
                    ? (isAvailability ? 'background' : 'auto')
                    : 'none');
            });
        };

        const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            locale: 'es',
            initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
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
            expandRows: true,
            nowIndicator: true,
            selectable: true,
            editable: true,
            eventDurationEditable: true,
            slotMinTime: '<?php echo escape_html($hours['opening']); ?>:00',
            slotMaxTime: '<?php echo escape_html($hours['closing']); ?>:00',
            slotDuration: '00:30:00',
            eventSources: [
                {
                    events: async (info, success, failure) => {
                        try {
                            const response = await fetch(`bookings/availability-feed.php?start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`);
                            success(await response.json());
                        } catch (error) {
                            failure(error);
                        }
                    }
                },
                {
                    events: async (_info, success, failure) => {
                        try {
                            const response = await fetch('bookings/staff-feed.php');
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
                    }
                }
            ],
            dateClick: (info) => {
                document.getElementById('dia').value = formatDate(info.date);
                document.getElementById('hora_comienzo').value = formatTime(info.date);
            },
            eventClick: ({ event }) => {
                if (event.display === 'background') {
                    return;
                }
                selectedEvent = event;
                const props = event.extendedProps;
                document.getElementById('modal-title').textContent = event.title;
                document.getElementById('modal-customer').textContent = `${props.nombre_cliente || ''} ${props.apellido_cliente || ''}`.trim() || '-';
                document.getElementById('modal-discipline').textContent = props.nombre_disciplina || 'General';
                document.getElementById('modal-phone').textContent = props.telefono_cliente || '-';
                document.getElementById('modal-service').textContent = props.nombre_servicio || '-';
                document.getElementById('modal-status-select').value = props.estado_reserva || 'confirmada';
                document.getElementById('modal-notes-input').value = props.notas_reserva || '';
                document.getElementById('modal-start-input').value = toLocalInputValue(event.start);
                document.getElementById('modal-end-input').value = toLocalInputValue(event.end);
                modal.showModal();
            },
            eventDrop: async ({ event, revert }) => {
                if (event.display === 'background') {
                    revert();
                    return;
                }
                try {
                    await persistEvent(event);
                } catch (error) {
                    alert(error.message || 'No se pudo mover la reserva');
                    revert();
                }
            },
            eventResize: async ({ event, revert }) => {
                if (event.display === 'background') {
                    revert();
                    return;
                }
                try {
                    await persistEvent(event);
                } catch (error) {
                    alert(error.message || 'No se pudo ajustar la reserva');
                    revert();
                }
            },
            loading: () => {
                setTimeout(applyFilters, 0);
            }
        });
        calendar.render();

        async function persistEvent(event, payload = {}) {
            const response = await fetch('bookings/api.php?accion=update_event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    id_evento: event.id,
                    start: payload.start || event.start.toISOString(),
                    end: payload.end || event.end.toISOString(),
                    estado_reserva: payload.estado_reserva || '',
                    notas_reserva: payload.notas_reserva || '',
                    csrf_token: csrfToken
                }).toString()
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'No se pudo actualizar la reserva');
            }
        }

        [stateFilter, serviceFilter, displayFilter].forEach((filter) => {
            filter.addEventListener('change', applyFilters);
        });

        document.getElementById('btn_reservar').addEventListener('click', async () => {
            const payload = new URLSearchParams({
                id_cliente: document.getElementById('txt_cliente').value,
                id_servicio: document.getElementById('txt_servicio').value,
                dia: document.getElementById('dia').value,
                hora: document.getElementById('hora_comienzo').value,
                notas_reserva: document.getElementById('notas_reserva').value,
                csrf_token: csrfToken
            });
            const response = await fetch('bookings/api.php?accion=agendar_staff', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
                body: payload.toString()
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                alert(result.error || 'No se pudo crear la reserva');
                return;
            }
            calendar.refetchEvents();
            document.getElementById('pro-booking-form').reset();
        });

        document.getElementById('modal-save-button').addEventListener('click', async () => {
            if (!selectedEvent) {
                return;
            }
            try {
                await persistEvent(selectedEvent, {
                    start: new Date(document.getElementById('modal-start-input').value).toISOString(),
                    end: new Date(document.getElementById('modal-end-input').value).toISOString(),
                    estado_reserva: document.getElementById('modal-status-select').value,
                    notas_reserva: document.getElementById('modal-notes-input').value
                });
                modal.close();
                calendar.refetchEvents();
            } catch (error) {
                alert(error.message || 'No se pudo guardar la reserva');
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
            modal.close();
            selectedEvent = null;
            calendar.refetchEvents();
        });
    </script>
</body>
</html>
