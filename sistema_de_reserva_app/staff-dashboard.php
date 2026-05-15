<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_role('id_professional', '2', 'staff-login.php');

$pdo = app_pdo();
$theme = current_theme();
$settings = app_settings($pdo);
$csrfToken = csrf_token();
$professionalId = request_session_int('id_professional');
$professional = fetch_one($pdo->prepare('SELECT * FROM professionals WHERE id_professional = :id'), array(':id' => $professionalId));
$professionalDisciplineId = (int) ($professional['id_disciplina'] ?? 0);
$clientes = fetch_all($pdo->prepare('SELECT id_cliente, nombre_cliente, apellido_cliente FROM clientes ORDER BY nombre_cliente, apellido_cliente'));
$servicios = fetch_all(
    $pdo->prepare(
        'SELECT servicios.*, disciplinas.nombre_disciplina
         FROM servicios
         JOIN professional_services ON professional_services.id_servicio = servicios.id_servicio
         LEFT JOIN disciplinas ON servicios.id_disciplina = disciplinas.id_disciplina
         WHERE servicios.activo = 1
           AND professional_services.id_professional = :id_professional
         ORDER BY servicios.nombre_servicio'
    ),
    array(':id_professional' => $professionalId)
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
    <?php render_flash_messages(); ?>
    <header class="sticky top-0 z-40 border-b border-white/60 bg-white/75 backdrop-blur-2xl">
        <div class="app-shell flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-sm font-semibold text-white shadow-soft" style="background: linear-gradient(135deg, var(--accent), var(--primary));">
                    <?php echo escape_html(strtoupper(substr((string) ($_SESSION['name_professional'] ?? 'P'), 0, 1))); ?>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Agenda profesional</div>
                    <h1 class="truncate text-lg font-semibold text-slate-950"><?php echo escape_html((string) ($_SESSION['name_professional'] ?? 'Profesional')); ?></h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="staff/profile.php" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-950">Mi perfil</a>
                <a href="logout.php" class="rounded-full px-4 py-2 text-sm font-semibold text-white" style="background: linear-gradient(135deg, var(--accent), var(--primary));">Salir</a>
            </div>
        </div>
    </header>

    <main class="app-shell grid max-w-[1500px] gap-6 py-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.8fr)]">
        <section class="surface-card surface-card-body">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Calendario</div>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Disponibilidad y reservas en una sola vista</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Gestiona tu dia con drag and drop, filtros rapidos y creacion de reservas con feedback inmediato.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm text-slate-500">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Horario <?php echo escape_html($hours['opening']); ?> - <?php echo escape_html($hours['closing']); ?></span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"><?php echo escape_html((string) $hours['slot_interval']); ?> min</span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"><?php echo escape_html($scheduleSummary); ?></span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2"><?php echo $professionalDisciplineId > 0 ? 'Servicios compatibles' : 'Sin disciplina asignada'; ?></span>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="block text-sm font-medium text-slate-600">
                        Estado
                        <select id="status-filter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                            <option value="">Todos</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="en_progreso">En progreso</option>
                            <option value="completada">Completada</option>
                            <option value="no_asistio">No asistio</option>
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
                        <?php if ($servicios === array()): ?>
                            <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">No tienes servicios activos compatibles. Administracion debe asignarte una disciplina o crear servicios para tu disciplina.</div>
                        <?php endif; ?>
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

        <aside class="min-w-0 space-y-6">
            <section class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Nueva reserva</div>
                <h3 class="mt-2 text-xl font-semibold">Agendamiento rÃ¡pido</h3>
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
                                <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>" data-disciplina="<?php echo escape_html((string) ($servicio['id_disciplina'] ?? '')); ?>"><?php echo escape_html($servicio['nombre_servicio'] . ' - ' . $servicio['duracion_minutos'] . ' min'); ?></option>
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
                    <div id="booking-feedback" class="hidden rounded-2xl border px-4 py-3 text-sm" role="status" aria-live="polite"></div>
                    <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95 disabled:cursor-wait disabled:opacity-70" style="background: linear-gradient(135deg, var(--accent), var(--primary));" id="btn_reservar" type="submit">
                        Crear reserva
                    </button>
                </form>
            </section>

            <section class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Lectura visual</div>
                <div class="mt-4 space-y-3 text-sm leading-7 text-slate-600">
                    <p><span class="inline-block h-3 w-3 rounded-full bg-slate-300"></span> Bloques grises: tiempo no laborable.</p>
                    <p><span class="inline-block h-3 w-3 rounded-full bg-orange-200"></span> Bloques naranjos: pausas del profesional.</p>
                    <p>Usa el modal para actualizar, reprogramar o eliminar reservas sin salir del calendario.</p>
                </div>
            </section>
        </aside>
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
            <div class="mt-6 grid gap-4 sm:grid-cols-2 text-sm text-slate-600">
                <div><strong class="block text-slate-900">Cliente</strong><span id="modal-customer"></span></div>
                <div><strong class="block text-slate-900">Disciplina</strong><span id="modal-discipline"></span></div>
                <div><strong class="block text-slate-900">TelÃ©fono</strong><span id="modal-phone"></span></div>
                <div><strong class="block text-slate-900">Servicio</strong><span id="modal-service"></span></div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-600">
                    Inicio
                    <input id="modal-start-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    TÃ©rmino
                    <input id="modal-end-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Estado
                    <select id="modal-status-select" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                        <option value="confirmada">Confirmada</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_progreso">En progreso</option>
                        <option value="completada">Completada</option>
                        <option value="no_asistio">No asistio</option>
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
        const feedbackBox = document.getElementById('booking-feedback');
        const bookingButton = document.getElementById('btn_reservar');
        const bookingForm = document.getElementById('pro-booking-form');
        let selectedEvent = null;

        const pad = (value) => String(value).padStart(2, '0');
        const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const formatTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
        const toLocalInputValue = (date) => {
            const localDate = new Date(date);
            localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
            return localDate.toISOString().slice(0, 16);
        };
        const showFeedback = (message, type = 'info') => {
            const tones = {
                success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
                error: 'border-rose-200 bg-rose-50 text-rose-900',
                info: 'border-slate-200 bg-slate-50 text-slate-700'
            };
            feedbackBox.className = `rounded-2xl border px-4 py-3 text-sm ${tones[type] || tones.info}`;
            feedbackBox.textContent = message;
            window.clearTimeout(showFeedback.timeoutId);
            showFeedback.timeoutId = window.setTimeout(() => {
                feedbackBox.classList.add('hidden');
            }, type === 'error' ? 7000 : 4200);
        };
        const parseJsonResponse = async (response) => {
            const text = await response.text();
            try {
                return text === '' ? {} : JSON.parse(text);
            } catch (_error) {
                return { ok: false, error: text || 'Respuesta invalida del servidor' };
            }
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
            initialView: window.innerWidth < 768 ? 'listWeek' : 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'DÃ­a',
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

        bookingForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (bookingButton.disabled) {
                return;
            }
            if (!bookingForm.checkValidity()) {
                bookingForm.reportValidity();
                showFeedback('Completa cliente, servicio, fecha y hora antes de crear la reserva.', 'error');
                return;
            }
            bookingButton.disabled = true;
            bookingButton.textContent = 'Creando...';
            const payload = new URLSearchParams({
                id_cliente: document.getElementById('txt_cliente').value,
                id_servicio: document.getElementById('txt_servicio').value,
                dia: document.getElementById('dia').value,
                hora: document.getElementById('hora_comienzo').value,
                notas_reserva: document.getElementById('notas_reserva').value,
                estado_reserva: 'confirmada',
                csrf_token: csrfToken
            });
            try {
                const response = await fetch('bookings/api.php?accion=agendar_staff', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
                    body: payload.toString()
                });
                const result = await parseJsonResponse(response);
                if (!response.ok || !result.ok) {
                    showFeedback(result.error || 'No se pudo crear la reserva. Revisa disponibilidad y vuelve a intentar.', 'error');
                    return;
                }
                calendar.refetchEvents();
                bookingForm.reset();
                showFeedback('Reserva creada correctamente en tu calendario.', 'success');
            } catch (error) {
                showFeedback(error.message || 'No se pudo contactar al servidor.', 'error');
            } finally {
                bookingButton.disabled = false;
                bookingButton.textContent = 'Crear reserva';
            }
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
            if (!selectedEvent || !confirm('Â¿Eliminar esta reserva?')) {
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
