<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

redirect_to_frontend('/cliente');

require_role('id_cliente', '3', 'index.php');

$pdo = app_pdo();
$theme = current_theme();
$settings = app_settings($pdo);
$csrfToken = csrf_token();
$disciplinas = fetch_all($pdo->prepare('SELECT * FROM disciplinas WHERE activa = 1 ORDER BY nombre_disciplina'));
$profesionales = fetch_all(
    $pdo->prepare(
        'SELECT professionals.*,
                GROUP_CONCAT(DISTINCT professional_disciplines.id_disciplina) AS discipline_ids,
                GROUP_CONCAT(DISTINCT professional_services.id_servicio) AS service_ids
         FROM professionals
         LEFT JOIN professional_disciplines ON professional_disciplines.id_professional = professionals.id_professional
         LEFT JOIN professional_services ON professional_services.id_professional = professionals.id_professional
         WHERE professionals.activo = 1
         GROUP BY professionals.id_professional
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
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => $settings['brand_favicon'] ?? '',
            'fullcalendar' => true,
            'body_background' => 'radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 14%, transparent), transparent 25%), linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%)',
        )
    ); ?>
    <style>
        .fc .fc-toolbar-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .fc .fc-button { border-radius: 999px; border: 0; box-shadow: none; padding: 0.7rem 1rem; background: #e2e8f0; color: #0f172a; }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active { background: var(--secondary); }
        .fc .fc-scrollgrid, .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(148, 163, 184, 0.22); }
        .fc-event { border: 0; border-radius: 16px; padding: 4px 6px; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10); }
    </style>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <header class="premium-topbar">
        <div class="app-shell flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="premium-brand-mark">
                    <?php echo escape_html(strtoupper(substr((string) ($_SESSION['nombre_cliente'] ?? 'C'), 0, 1))); ?>
                </div>
                <div class="min-w-0">
                    <div class="section-eyebrow">Portal cliente</div>
                    <h1 class="truncate text-lg font-semibold text-slate-950"><?php echo escape_html($_SESSION['nombre_cliente'] . ' ' . $_SESSION['apellido_cliente']); ?></h1>
                </div>
            </div>
            <div class="premium-nav">
                <a href="customer/profile.php" class="premium-nav-link">Mi perfil</a>
                <a href="logout.php" class="premium-nav-link premium-nav-link-active">Salir</a>
            </div>
        </div>
    </header>

    <main class="app-shell max-w-[1500px] space-y-8 py-6 sm:py-8">
        <section class="surface-card surface-card-body">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="min-w-0">
                    <div class="section-eyebrow">Experiencia cliente</div>
                    <h2 class="page-title max-w-4xl text-balance">Reserva con contexto, sin perder de vista tu agenda.</h2>
                    <p class="page-copy">Primero elige disciplina, servicio y profesional. Luego revisa disponibilidad y confirma el horario con feedback inmediato.</p>
                    <div class="mt-4 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">UI cliente renovada</div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:min-w-72">
                    <a href="#nueva-reserva" class="btn-primary">Nueva reserva</a>
                    <a href="#agenda-cliente" class="btn-secondary">Ver agenda</a>
                </div>
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1.35fr)_minmax(24rem,0.75fr)] xl:items-start">
        <section id="agenda-cliente" class="surface-card surface-card-body">
            <div class="flex flex-col gap-5 border-b border-slate-200 pb-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Agenda visual</div>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Reservas y disponibilidad</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Crea una reserva guiada y revisa tus cambios sin salir del calendario.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm text-slate-500">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Horario <?php echo escape_html($hours['opening']); ?> - <?php echo escape_html($hours['closing']); ?></span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Tus reservas se editan desde el modal</span>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block text-sm font-medium text-slate-600">
                        Profesional visible
                        <select id="availability-professional" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                            <option value="">Sin overlay</option>
                            <?php foreach ($profesionales as $profesional): ?>
                                <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>"><?php echo escape_html($profesional['name_professional']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
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
                        Vista
                        <select id="display-filter" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3">
                            <option value="all">Todo</option>
                            <option value="mine">Solo mis reservas</option>
                            <option value="availability">Solo disponibilidad</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="calendar-frame mt-6" id="calendar">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Agenda compacta</div>
                            <h3 class="mt-2 text-lg font-semibold text-slate-950">Tus proximas reservas</h3>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600">Vista segura sin dependencias externas</span>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        <?php if ($reservas === array()): ?>
                            <?php render_empty_state('Sin reservas visibles', 'Usa el formulario de la derecha para crear tu primera reserva.'); ?>
                        <?php else: ?>
                            <?php foreach (array_slice($reservas, 0, 8) as $reserva): ?>
                                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $reserva['nombre_servicio']); ?></div>
                            <div class="mt-1 break-words text-sm text-slate-500"><?php echo escape_html((string) $reserva['name_professional']); ?> · <?php echo escape_html((string) ($reserva['nombre_disciplina'] ?: 'General')); ?></div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"><?php echo escape_html((string) $reserva['estado_reserva']); ?></span>
                                    </div>
                                    <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"><?php echo escape_html((string) $reserva['start']); ?></div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <aside class="min-w-0 space-y-8">
            <section id="nueva-reserva" class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Nueva reserva</div>
                <h3 class="mt-2 text-xl font-semibold">Wizard de reserva</h3>
                <div class="mt-5 grid gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:grid-cols-5 xl:grid-cols-1 2xl:grid-cols-5">
                    <div class="rounded-full border border-slate-200 bg-white px-3 py-2 text-center">1 Disciplina</div>
                    <div class="rounded-full border border-slate-200 bg-white px-3 py-2 text-center">2 Servicio</div>
                    <div class="rounded-full border border-slate-200 bg-white px-3 py-2 text-center">3 Profesional</div>
                    <div class="rounded-full border border-slate-200 bg-white px-3 py-2 text-center">4 Horario</div>
                    <div class="rounded-full border border-slate-200 bg-white px-3 py-2 text-center">5 Confirmar</div>
                </div>
                <form id="client-booking-form" class="mt-6 space-y-5">
                    <label class="block text-sm font-medium text-slate-600">Disciplina
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" id="disciplina_cliente">
                            <option value="">Todas</option>
                            <?php foreach ($disciplinas as $disciplina): ?>
                                <option value="<?php echo escape_html((string) $disciplina['id_disciplina']); ?>"><?php echo escape_html($disciplina['nombre_disciplina']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Servicio
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" id="txt_servicio" required>
                            <option value="">Selecciona servicio</option>
                            <?php foreach ($servicios as $servicio): ?>
                                <option value="<?php echo escape_html((string) $servicio['id_servicio']); ?>" data-disciplina="<?php echo escape_html((string) ($servicio['id_disciplina'] ?? '')); ?>">
                                    <?php echo escape_html($servicio['nombre_servicio'] . ' · ' . $servicio['duracion_minutos'] . ' min'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600">Profesional
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" id="professional_id" required>
                            <option value="">Selecciona profesional</option>
                            <?php foreach ($profesionales as $profesional): ?>
                                <option value="<?php echo escape_html((string) $profesional['id_professional']); ?>" data-disciplina="<?php echo escape_html((string) ($profesional['discipline_ids'] ?? '')); ?>" data-services="<?php echo escape_html((string) ($profesional['service_ids'] ?? '')); ?>">
                                    <?php echo escape_html((string) $profesional['name_professional']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-600">Fecha
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" id="dia" type="date" required>
                        </label>
                        <label class="block text-sm font-medium text-slate-600">Hora
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" id="hora_comienzo" type="time" min="<?php echo escape_html($hours['opening']); ?>" max="<?php echo escape_html($hours['closing']); ?>" step="<?php echo escape_html((string) ($hours['slot_interval'] * 60)); ?>" required>
                        </label>
                    </div>
                    <label class="block text-sm font-medium text-slate-600">Notas
                        <textarea class="mt-2 min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3" id="notas_reserva"></textarea>
                    </label>
                    <div id="booking-review" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        Selecciona disciplina, servicio, profesional y horario para revisar la confirmacion antes de enviar.
                    </div>
                    <div id="booking-feedback" class="hidden rounded-2xl border px-4 py-3 text-sm" role="status" aria-live="polite"></div>
                    <button class="inline-flex w-full items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95 disabled:cursor-wait disabled:opacity-70" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" id="btn_reservar" type="submit">
                        Reservar horario
                    </button>
                </form>
            </section>

            <section class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Proximas reservas</div>
                <div class="mt-5 space-y-4">
                    <?php if ($reservas === array()): ?>
                        <?php render_empty_state('Sin reservas aun', 'Cuando agendes un servicio, aparecera aqui con su profesional y horario.'); ?>
                    <?php else: ?>
                        <?php foreach (array_slice($reservas, 0, 4) as $reserva): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="font-semibold"><?php echo escape_html($reserva['nombre_servicio']); ?></div>
                                <div class="mt-1 text-sm text-slate-500"><?php echo escape_html($reserva['name_professional']); ?></div>
                                <div class="mt-2 text-sm text-slate-600"><?php echo escape_html($reserva['start']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
        </section>
    </main>

    <dialog id="booking-modal" class="dialog-shell">
        <div class="dialog-body">
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
                <div><strong class="block text-slate-900">Servicio</strong><span id="modal-service"></span></div>
                <div><strong class="block text-slate-900">Estado</strong><span id="modal-status-label"></span></div>
            </div>
            <div id="modal-owned-editor" class="mt-6 hidden grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-600">
                    Inicio
                    <input id="modal-start-input" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3" type="datetime-local">
                </label>
                <label class="block text-sm font-medium text-slate-600">
                    Termino
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
                    Notas
                    <textarea id="modal-notes-input" class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3"></textarea>
                </label>
            </div>
            <div id="modal-owned-actions" class="mt-6 hidden flex-col gap-3 sm:flex-row">
                <button id="modal-save-button" class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white" style="background: linear-gradient(135deg, var(--secondary), var(--primary));" type="button">Guardar cambios</button>
                <button id="modal-delete-button" class="inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-600" type="button">Cancelar reserva</button>
            </div>
            <p id="modal-readonly-note" class="mt-6 text-sm text-slate-500">Este bloque corresponde a una reserva ocupada en la agenda.</p>
        </div>
    </dialog>

    <script>
        const modal = document.getElementById('booking-modal');
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        const ownedReservationIds = new Set(<?php echo json_encode($ownedReservationIds); ?>.map((value) => Number(value)));
        const availabilityProfessional = document.getElementById('availability-professional');
        const stateFilter = document.getElementById('status-filter');
        const displayFilter = document.getElementById('display-filter');
        const reviewBox = document.getElementById('booking-review');
        const feedbackBox = document.getElementById('booking-feedback');
        const bookingButton = document.getElementById('btn_reservar');
        const bookingForm = document.getElementById('client-booking-form');
        let selectedEvent = null;

        const pad = (value) => String(value).padStart(2, '0');
        const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const formatTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;
        const formatDateTime = (date) => new Date(date).toLocaleString('es-CL', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        const toLocalInputValue = (date) => {
            const localDate = new Date(date);
            localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
            return localDate.toISOString().slice(0, 16);
        };
        const refreshReview = () => {
            const serviceLabel = document.getElementById('txt_servicio').selectedOptions[0]?.textContent || 'Pendiente';
            const professionalLabel = document.getElementById('professional_id').selectedOptions[0]?.textContent || 'Pendiente';
            const day = document.getElementById('dia').value || 'Pendiente';
            const time = document.getElementById('hora_comienzo').value || '--:--';
            reviewBox.textContent = `Confirmacion: ${serviceLabel} · ${professionalLabel} · ${day} ${time}`;
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
            }, type === 'success' ? 12000 : (type === 'error' ? 9000 : 7000));
        };
        const parseJsonResponse = async (response) => {
            const text = await response.text();
            try {
                return text === '' ? {} : JSON.parse(text);
            } catch (_error) {
                return { ok: false, error: text || 'Respuesta invalida del servidor' };
            }
        };
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));

        const availabilitySource = {
            id: 'availability',
            events: async (info, success, failure) => {
                const professionalId = availabilityProfessional.value;
                if (!professionalId) {
                    success([]);
                    return;
                }
                try {
                    const response = await fetch(`bookings/availability-feed.php?professional_id=${encodeURIComponent(professionalId)}&start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`);
                    success(await response.json());
                } catch (error) {
                    failure(error);
                }
            }
        };

        const calendarElement = document.getElementById('calendar');
        const renderAgendaFallback = async () => {
            try {
                const response = await fetch('bookings/public-feed.php');
                const events = await response.json();
                const visibleEvents = events.slice(0, 12);
                calendarElement.innerHTML = `
                    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        La agenda visual avanzada no cargo desde la libreria externa. Se muestra una agenda compacta local para que el formulario siga funcionando.
                    </div>
                    <div class="mt-4 grid gap-3">
                        ${visibleEvents.map((event) => `
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="font-semibold text-slate-950">${escapeHtml(event.nombre_servicio || 'Reserva')}</div>
                                <div class="mt-1 text-sm text-slate-500">${escapeHtml(event.name_professional || '')} · ${escapeHtml(event.nombre_disciplina || 'General')}</div>
                                <div class="mt-2 text-sm text-slate-700">${escapeHtml(event.start || '')}</div>
                            </article>
                        `).join('') || '<div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-500">No hay reservas visibles todavia.</div>'}
                    </div>
                `;
            } catch (_error) {
                calendarElement.innerHTML = '<div class="rounded-3xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">No se pudo cargar la agenda. El formulario de reserva sigue disponible.</div>';
            }
        };
        let calendar = {
            getEvents: () => [],
            refetchEvents: renderAgendaFallback,
            getEventSourceById: () => null
        };
        function applyFilters() {
            calendar.getEvents().forEach((event) => {
                const props = event.extendedProps;
                const isAvailability = ['day_off', 'closed', 'break'].includes(props.kind || '');
                const isMine = ownedReservationIds.has(Number(event.id));
                const stateMatch = stateFilter.value === '' || props.estado_reserva === stateFilter.value;
                const displayMatch =
                    displayFilter.value === 'all' ||
                    (displayFilter.value === 'mine' && isMine) ||
                    (displayFilter.value === 'availability' && isAvailability);

                event.setProp('display', displayMatch && (isAvailability || stateMatch)
                    ? (isAvailability ? 'background' : 'auto')
                    : 'none');
            });
        }

        if (window.FullCalendar) {
            calendarElement.innerHTML = '';
            calendar = new FullCalendar.Calendar(calendarElement, {
            locale: 'es',
            initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth',
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
            selectable: true,
            slotMinTime: '<?php echo escape_html($hours['opening']); ?>:00',
            slotMaxTime: '<?php echo escape_html($hours['closing']); ?>:00',
            slotDuration: '00:30:00',
            eventSources: [
                availabilitySource,
                {
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
                    }
                }
            ],
            dateClick: (info) => {
                const timeInput = document.getElementById('hora_comienzo');
                document.getElementById('dia').value = formatDate(info.date);
                timeInput.value = info.allDay
                    ? (timeInput.value || '<?php echo escape_html($hours['opening']); ?>')
                    : formatTime(info.date);
                refreshReview();
            },
            loading: () => {
                setTimeout(applyFilters, 0);
            },
            eventClick: ({ event }) => {
                const props = event.extendedProps;
                const isAvailability = ['day_off', 'closed', 'break'].includes(props.kind || '');
                const reservationId = Number(event.id);
                const isOwned = ownedReservationIds.has(reservationId);

                document.getElementById('modal-title').textContent = event.title || 'Disponibilidad';
                document.getElementById('modal-professional').textContent = props.name_professional || 'No laborable';
                document.getElementById('modal-discipline').textContent = props.nombre_disciplina || 'General';
                document.getElementById('modal-service').textContent = props.nombre_servicio || (isAvailability ? 'Bloque de disponibilidad' : '-');
                document.getElementById('modal-status-label').textContent = props.estado_reserva || (isAvailability ? 'No disponible' : 'confirmada');

                const editor = document.getElementById('modal-owned-editor');
                const actions = document.getElementById('modal-owned-actions');
                const note = document.getElementById('modal-readonly-note');
                const canEdit = isOwned && !isAvailability;

                editor.classList.toggle('hidden', !canEdit);
                actions.classList.toggle('hidden', !canEdit);
                actions.classList.toggle('flex', canEdit);

                if (canEdit) {
                    selectedEvent = event;
                    document.getElementById('modal-start-input').value = toLocalInputValue(event.start);
                    document.getElementById('modal-end-input').value = toLocalInputValue(event.end);
                    document.getElementById('modal-status-select').value = props.estado_reserva || 'confirmada';
                    document.getElementById('modal-notes-input').value = props.notas_reserva || '';
                    note.textContent = `Horario actual: ${formatDateTime(event.start)} - ${formatDateTime(event.end)}`;
                } else {
                    selectedEvent = null;
                    note.textContent = isAvailability
                        ? 'Este bloque muestra tiempo no disponible o pausas del profesional seleccionado.'
                        : 'Este bloque corresponde a una reserva ocupada en la agenda.';
                }

                modal.showModal();
            }
            });
            calendar.render();
            const compactAgenda = document.createElement('div');
            compactAgenda.className = 'mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600';
            compactAgenda.textContent = 'Tip: si el calendario visual no muestra eventos, usa los filtros de Estado/Vista o crea una reserva desde el formulario.';
            calendarElement.appendChild(compactAgenda);
        } else {
            renderAgendaFallback();
        }

        [availabilityProfessional, stateFilter, displayFilter].forEach((element) => {
            element.addEventListener('change', () => {
                if (element === availabilityProfessional) {
                    calendar.getEventSourceById('availability')?.refetch();
                }
                applyFilters();
            });
        });

        const applyDisciplineFilter = () => {
            const discipline = String(document.getElementById('disciplina_cliente').value);
            let availableServices = 0;
            let availableProfessionals = 0;
            ['txt_servicio', 'professional_id'].forEach((selectId) => {
                const select = document.getElementById(selectId);
                Array.from(select.options).forEach((option, index) => {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }
                    const disciplineIds = String(option.dataset.disciplina || '').split(',').filter(Boolean);
                    const serviceIds = String(option.dataset.services || '').split(',').filter(Boolean);
                    const selectedService = document.getElementById('txt_servicio').value;
                    const matchesDiscipline = discipline === '' || disciplineIds.includes(discipline);
                    const matchesService = selectId !== 'professional_id' || selectedService === '' || serviceIds.includes(selectedService);
                    const matches = matchesDiscipline && matchesService;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (matches && selectId === 'txt_servicio') {
                        availableServices++;
                    }
                    if (matches && selectId === 'professional_id') {
                        availableProfessionals++;
                    }
                });
                if (select.selectedOptions[0]?.disabled) {
                    select.value = '';
                }
            });
            if (discipline !== '' && (availableServices === 0 || availableProfessionals === 0)) {
                showFeedback('La disciplina seleccionada no tiene servicios o profesionales activos. Prueba con otra disciplina.', 'error');
            }
            refreshReview();
        };
        const syncDisciplineFromService = () => {
            const service = document.getElementById('txt_servicio');
            const discipline = service.selectedOptions[0]?.dataset.disciplina || '';
            if (discipline !== '') {
                document.getElementById('disciplina_cliente').value = discipline;
                applyDisciplineFilter();
            }
        };
        const ensureCompatibleProfessional = () => {
            const selectedService = document.getElementById('txt_servicio').value;
            const serviceDiscipline = document.getElementById('txt_servicio').selectedOptions[0]?.dataset.disciplina || '';
            const professional = document.getElementById('professional_id');
            const professionalServices = String(professional.selectedOptions[0]?.dataset.services || '').split(',').filter(Boolean);
            if (selectedService !== '' && professional.value !== '' && !professionalServices.includes(selectedService)) {
                professional.value = '';
                showFeedback('Ese profesional no realiza el servicio seleccionado. Elige un profesional compatible.', 'error');
            }
            refreshReview();
        };

        document.getElementById('disciplina_cliente').addEventListener('change', () => {
            applyDisciplineFilter();
        });
        applyDisciplineFilter();
        document.getElementById('txt_servicio').addEventListener('change', () => {
            syncDisciplineFromService();
            ensureCompatibleProfessional();
        });
        document.getElementById('professional_id').addEventListener('change', ensureCompatibleProfessional);
        ['txt_servicio', 'professional_id', 'dia', 'hora_comienzo', 'notas_reserva'].forEach((id) => {
            document.getElementById(id).addEventListener('change', refreshReview);
        });

        bookingForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (bookingButton.disabled) {
                return;
            }
            if (!bookingForm.checkValidity()) {
                bookingForm.reportValidity();
                showFeedback('Completa servicio, profesional, fecha y hora antes de reservar.', 'error');
                return;
            }
            bookingButton.disabled = true;
            bookingButton.textContent = 'Reservando...';
            const payload = new URLSearchParams({
                professional_id: document.getElementById('professional_id').value,
                id_servicio: document.getElementById('txt_servicio').value,
                dia: document.getElementById('dia').value,
                hora: document.getElementById('hora_comienzo').value,
                notas_reserva: document.getElementById('notas_reserva').value,
                estado_reserva: 'pendiente',
                waitlist_on_failure: '1',
                csrf_token: csrfToken
            });
            try {
                const response = await fetch('bookings/api.php?accion=agendar_customer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    body: payload.toString()
                });
                const result = await parseJsonResponse(response);
                if (!response.ok || !result.ok) {
                    showFeedback(result.error || 'No se pudo crear la reserva. Revisa disponibilidad y vuelve a intentar.', 'error');
                    return;
                }
                if (result.waitlist) {
                    showFeedback(result.message || 'La solicitud fue enviada a lista de espera.', 'info');
                    bookingForm.reset();
                    refreshReview();
                    return;
                }
                const reservedDay = document.getElementById('dia').value;
                const reservedTime = document.getElementById('hora_comienzo').value;
                const reservedService = document.getElementById('txt_servicio').selectedOptions[0]?.textContent.trim() || 'Servicio';
                const reservedProfessional = document.getElementById('professional_id').selectedOptions[0]?.textContent.trim() || 'Profesional';
                if (result.id_evento) {
                    ownedReservationIds.add(Number(result.id_evento));
                }
                calendar.refetchEvents();
                bookingForm.reset();
                refreshReview();
                showFeedback(`Reserva solicitada para ${reservedDay} a las ${reservedTime}. ${reservedService} con ${reservedProfessional}. La veras reflejada en tu agenda.`, 'success');
            } catch (error) {
                showFeedback(error.message || 'No se pudo contactar al servidor.', 'error');
            } finally {
                bookingButton.disabled = false;
                bookingButton.textContent = 'Reservar horario';
            }
        });
        refreshReview();

        document.getElementById('modal-save-button').addEventListener('click', async () => {
            if (!selectedEvent) {
                return;
            }
            const response = await fetch('bookings/api.php?accion=update_event_customer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    id_evento: selectedEvent.id,
                    start: new Date(document.getElementById('modal-start-input').value).toISOString(),
                    end: new Date(document.getElementById('modal-end-input').value).toISOString(),
                    estado_reserva: document.getElementById('modal-status-select').value,
                    notas_reserva: document.getElementById('modal-notes-input').value,
                    csrf_token: csrfToken
                }).toString()
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                showFeedback(result.error || 'No se pudo actualizar la reserva', 'error');
                return;
            }
            modal.close();
            calendar.refetchEvents();
            showFeedback('Reserva actualizada correctamente.', 'success');
        });

        document.getElementById('modal-delete-button').addEventListener('click', async () => {
            if (!selectedEvent || !(await window.appConfirm('Cancelar esta reserva?'))) {
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
                showFeedback(result.error || 'No se pudo cancelar la reserva', 'error');
                return;
            }
            modal.close();
            calendar.refetchEvents();
            showFeedback('Reserva cancelada correctamente.', 'success');
        });
    </script>
</body>
</html>
