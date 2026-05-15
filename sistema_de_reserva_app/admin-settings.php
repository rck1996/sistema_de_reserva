<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_role('id_admin', '1', 'admin-login.php');

$pdo = app_pdo();
$theme = current_theme();
$settings = app_settings($pdo);
$brandLogo = trim((string) ($settings['brand_logo'] ?? ''));
$brandFavicon = trim((string) ($settings['brand_favicon'] ?? ''));
$brandCover = trim((string) ($settings['brand_cover'] ?? ''));
$feriados = fetch_all($pdo->prepare('SELECT * FROM global_holidays ORDER BY holiday_date DESC LIMIT 12'));
$notifications = fetch_all($pdo->prepare('SELECT * FROM notification_log ORDER BY created_at DESC LIMIT 10'));
$auditEntries = fetch_all($pdo->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10'));
$backupFiles = array();
$backupsDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';

if (is_dir($backupsDirectory)) {
    $backupFiles = array_map(
        static fn (SplFileInfo $file): string => $file->getFilename(),
        iterator_to_array(new FilesystemIterator($backupsDirectory, FilesystemIterator::SKIP_DOTS))
    );
    rsort($backupFiles);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuracion | <?php echo escape_html(app_brand_name()); ?></title>
    <?php render_shared_head_assets(
        $theme,
        array(
            'favicon' => $brandFavicon,
            'body_background' => 'radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 15%, transparent), transparent 24%), radial-gradient(circle at top right, color-mix(in srgb, var(--accent) 10%, transparent), transparent 22%), linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%)',
        )
    ); ?>
</head>
<body class="min-h-screen text-slate-900">
    <?php render_flash_messages(); ?>
    <header class="sticky top-0 z-40 border-b border-white/60 bg-white/75 backdrop-blur-2xl">
        <div class="app-shell flex flex-col gap-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <?php if ($brandLogo !== ''): ?>
                    <img src="assets/branding/<?php echo escape_html($brandLogo); ?>" alt="Logo" class="h-11 w-11 rounded-2xl object-cover shadow-sm">
                <?php else: ?>
                    <div class="h-11 w-11 rounded-2xl bg-slate-950 shadow-sm"></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Sistema</div>
                    <h1 class="truncate text-lg font-semibold text-slate-950">Configuracion</h1>
                </div>
            </div>
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="admin-dashboard.php" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300 hover:text-slate-950">Dashboard</a>
                <a href="#configuracion" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300 hover:text-slate-950">Identidad</a>
                <a href="#bloqueos" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-slate-600 transition hover:border-slate-300 hover:text-slate-950">Bloqueos</a>
                <a href="#operacion" class="rounded-full border border-slate-950 bg-slate-950 px-4 py-2 text-white transition hover:bg-slate-800">Operacion</a>
                <a href="logout.php" class="rounded-full px-4 py-2 font-semibold text-white" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">Salir</a>
            </nav>
        </div>
    </header>

    <main class="app-shell space-y-6 py-6">
        <section class="surface-card surface-card-body overflow-hidden">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Preferencias globales</div>
                    <h2 class="mt-2 max-w-4xl text-3xl font-semibold tracking-tight text-slate-950">Marca, agenda, bloqueos y operacion sin contaminar el dashboard.</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">Esta pantalla concentra ajustes de sistema. El dashboard queda reservado para operar reservas y revisar demanda.</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    <div class="font-semibold text-slate-950"><?php echo escape_html(app_display_name()); ?></div>
                    <div class="mt-1"><?php echo escape_html($settings['business_type'] ?? 'Centro de servicios'); ?></div>
                </div>
            </div>
        </section>

        <section id="configuracion" class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
            <form action="management/create.php?accion=configuracion" method="post" enctype="multipart/form-data" class="surface-card surface-card-body grid gap-5">
                <?php echo csrf_input(); ?>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Identidad</div>
                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">Marca y experiencia publica</h3>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600">Nombre interno<input class="field-input mt-2" name="app_name" value="<?php echo escape_html($settings['app_name'] ?? 'sistema_de_reserva'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Nombre visible<input class="field-input mt-2" name="business_name" value="<?php echo escape_html($settings['business_name'] ?? 'Sistema de Reserva'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Tipo de negocio<input class="field-input mt-2" name="business_type" value="<?php echo escape_html($settings['business_type'] ?? 'Centro de servicios'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Bajada corta<input class="field-input mt-2" name="business_tagline" value="<?php echo escape_html($settings['business_tagline'] ?? 'Reservas simples, operacion clara'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600 md:col-span-2">Titulo principal<input class="field-input mt-2" name="hero_title" value="<?php echo escape_html($settings['hero_title'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600 md:col-span-2">Subtitulo principal<input class="field-input mt-2" name="hero_subtitle" value="<?php echo escape_html($settings['hero_subtitle'] ?? ''); ?>"></label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block text-sm font-medium text-slate-600">Color primario<input class="field-input mt-2 h-12" name="primary_color" type="color" value="<?php echo escape_html($settings['primary_color'] ?? '#0f172a'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Color secundario<input class="field-input mt-2 h-12" name="secondary_color" type="color" value="<?php echo escape_html($settings['secondary_color'] ?? '#0891b2'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Color accent<input class="field-input mt-2 h-12" name="accent_color" type="color" value="<?php echo escape_html($settings['accent_color'] ?? '#10b981'); ?>"></label>
                    <input type="hidden" name="surface_color" value="<?php echo escape_html($settings['surface_color'] ?? '#ffffff'); ?>">
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600">Apertura<input class="field-input mt-2" name="opening_time" type="time" value="<?php echo escape_html($settings['opening_time'] ?? '09:00'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Cierre<input class="field-input mt-2" name="closing_time" type="time" value="<?php echo escape_html($settings['closing_time'] ?? '20:00'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Intervalo agenda<input class="field-input mt-2" name="slot_interval" type="number" min="5" step="5" value="<?php echo escape_html($settings['slot_interval'] ?? '30'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Buffer global<input class="field-input mt-2" name="global_buffer_min" type="number" min="0" step="5" value="<?php echo escape_html($settings['global_buffer_min'] ?? '0'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Recordatorio horas<input class="field-input mt-2" name="reminder_hours_before" type="number" min="1" value="<?php echo escape_html($settings['reminder_hours_before'] ?? '24'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Zona horaria<input class="field-input mt-2" name="app_timezone" value="<?php echo escape_html($settings['app_timezone'] ?? 'America/Santiago'); ?>"></label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600">Correo contacto<input class="field-input mt-2" name="contact_email" value="<?php echo escape_html($settings['contact_email'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Telefono contacto<input class="field-input mt-2" name="contact_phone" value="<?php echo escape_html($settings['contact_phone'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Ciudad<input class="field-input mt-2" name="business_city" value="<?php echo escape_html($settings['business_city'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Mensaje de reserva<input class="field-input mt-2" name="booking_notice" value="<?php echo escape_html($settings['booking_notice'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600 md:col-span-2">Direccion<input class="field-input mt-2" name="contact_address" value="<?php echo escape_html($settings['contact_address'] ?? ''); ?>"></label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600">Email administrativo<select class="field-input mt-2" name="notifications_email_enabled"><option value="1" <?php echo ($settings['notifications_email_enabled'] ?? '1') === '1' ? 'selected' : ''; ?>>Activo</option><option value="0" <?php echo ($settings['notifications_email_enabled'] ?? '1') === '0' ? 'selected' : ''; ?>>Inactivo</option></select></label>
                    <label class="block text-sm font-medium text-slate-600">WhatsApp administrativo<select class="field-input mt-2" name="notifications_whatsapp_enabled"><option value="1" <?php echo ($settings['notifications_whatsapp_enabled'] ?? '0') === '1' ? 'selected' : ''; ?>>Activo</option><option value="0" <?php echo ($settings['notifications_whatsapp_enabled'] ?? '0') === '0' ? 'selected' : ''; ?>>Inactivo</option></select></label>
                    <label class="block text-sm font-medium text-slate-600">Envio real por SMTP<select class="field-input mt-2" name="notifications_send_email"><option value="0" <?php echo ($settings['notifications_send_email'] ?? '0') === '0' ? 'selected' : ''; ?>>Simulado</option><option value="1" <?php echo ($settings['notifications_send_email'] ?? '0') === '1' ? 'selected' : ''; ?>>SMTP real</option></select></label>
                    <label class="block text-sm font-medium text-slate-600">Remitente<input class="field-input mt-2" name="smtp_from_name" value="<?php echo escape_html($settings['smtp_from_name'] ?? 'Sistema de Reserva'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">Correo remitente<input class="field-input mt-2" name="smtp_from_email" value="<?php echo escape_html($settings['smtp_from_email'] ?? 'notificaciones@sistema.local'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">SMTP host<input class="field-input mt-2" name="smtp_host" value="<?php echo escape_html($settings['smtp_host'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">SMTP puerto<input class="field-input mt-2" name="smtp_port" type="number" min="1" value="<?php echo escape_html($settings['smtp_port'] ?? '587'); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">SMTP usuario<input class="field-input mt-2" name="smtp_username" value="<?php echo escape_html($settings['smtp_username'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">SMTP clave<input class="field-input mt-2" name="smtp_password" type="password" value="<?php echo escape_html($settings['smtp_password'] ?? ''); ?>"></label>
                    <label class="block text-sm font-medium text-slate-600">SMTP cifrado<select class="field-input mt-2" name="smtp_encryption"><option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>STARTTLS</option><option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option><option value="none" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>Sin cifrado</option></select></label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block text-sm font-medium text-slate-600">Logo<input class="field-input mt-2" name="brand_logo" type="file" accept=".png,.jpg,.jpeg,.webp,.svg"></label>
                    <label class="block text-sm font-medium text-slate-600">Favicon<input class="field-input mt-2" name="brand_favicon" type="file" accept=".png,.ico"></label>
                    <label class="block text-sm font-medium text-slate-600">Portada<input class="field-input mt-2" name="brand_cover" type="file" accept=".png,.jpg,.jpeg,.webp"></label>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-lg" style="background: linear-gradient(135deg, var(--accent), var(--primary));" type="submit">Guardar configuracion</button>
                    <a href="admin-dashboard.php" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700">Volver al dashboard</a>
                </div>
            </form>

            <aside class="space-y-6">
                <section class="surface-card surface-card-body">
                    <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Preview</div>
                    <h3 class="mt-2 text-xl font-semibold text-slate-950">Assets cargados</h3>
                    <div class="mt-5 grid gap-3">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Logo</div>
                            <?php if ($brandLogo !== ''): ?><img src="assets/branding/<?php echo escape_html($brandLogo); ?>" alt="Logo" class="mt-3 h-24 w-full rounded-2xl bg-white object-contain p-3"><?php else: ?><div class="mt-3 rounded-2xl bg-white p-4 text-sm text-slate-500">Sin logo cargado</div><?php endif; ?>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Favicon</div>
                            <?php if ($brandFavicon !== ''): ?><img src="assets/branding/<?php echo escape_html($brandFavicon); ?>" alt="Favicon" class="mt-3 h-14 w-14 rounded-2xl bg-white object-contain p-2"><?php else: ?><div class="mt-3 rounded-2xl bg-white p-4 text-sm text-slate-500">Se usa favicon por defecto</div><?php endif; ?>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Portada</div>
                            <?php if ($brandCover !== ''): ?><img src="assets/branding/<?php echo escape_html($brandCover); ?>" alt="Portada" class="mt-3 h-36 w-full rounded-2xl object-cover"><?php else: ?><div class="mt-3 rounded-2xl bg-white p-4 text-sm text-slate-500">Sin portada cargada</div><?php endif; ?>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section id="bloqueos" class="grid gap-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <form action="management/create.php?accion=holiday" method="post" class="surface-card surface-card-body">
                <?php echo csrf_input(); ?>
                <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Bloqueos globales</div>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Feriados y cierres</h3>
                <div class="mt-5 grid gap-4">
                    <label class="block text-sm font-medium text-slate-600">Fecha<input class="field-input mt-2" name="holiday_date" type="date" required></label>
                    <label class="block text-sm font-medium text-slate-600">Nombre<input class="field-input mt-2" name="holiday_name" required></label>
                    <label class="block text-sm font-medium text-slate-600">Tipo<select class="field-input mt-2" name="is_closed"><option value="1">Cerrado todo el dia</option><option value="0">Bloqueo parcial</option></select></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-600">Desde<input class="field-input mt-2" name="start_time" type="time"></label>
                        <label class="block text-sm font-medium text-slate-600">Hasta<input class="field-input mt-2" name="end_time" type="time"></label>
                    </div>
                    <label class="block text-sm font-medium text-slate-600">Notas<textarea class="field-textarea mt-2 min-h-24" name="notes"></textarea></label>
                    <button class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Guardar bloqueo</button>
                </div>
            </form>

            <section class="surface-card surface-card-body">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Calendario</div>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950">Bloqueos recientes</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600"><?php echo count($feriados); ?></span>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <?php if ($feriados === array()): ?>
                        <?php render_empty_state('Sin bloqueos globales', 'Agrega feriados o cierres para que el calendario los pinte y los valide.'); ?>
                    <?php else: ?>
                        <?php foreach ($feriados as $feriado): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $feriado['holiday_name']); ?></div>
                                <div class="mt-1"><?php echo escape_html((string) $feriado['holiday_date']); ?> · <?php echo ((int) $feriado['is_closed']) === 1 ? 'Cerrado' : 'Parcial'; ?></div>
                                <?php if ((string) ($feriado['notes'] ?? '') !== ''): ?><div class="mt-1 break-words"><?php echo escape_html((string) $feriado['notes']); ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <section id="operacion" class="grid gap-6 xl:grid-cols-3">
            <section class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Exportacion</div>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Datos operativos</h3>
                <div class="mt-5 grid gap-3">
                    <a class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:border-slate-300" href="management/export.php?tipo=reservas&formato=csv">Reservas CSV</a>
                    <a class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:border-slate-300" href="management/export.php?tipo=clientes&formato=csv">Clientes CSV</a>
                    <a class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:border-slate-300" href="management/export.php?tipo=reservas&formato=pdf">Reservas PDF</a>
                </div>
            </section>

            <section class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Backups</div>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">SQLite</h3>
                <form action="management/maintenance.php?accion=backup" method="post" class="mt-5">
                    <?php echo csrf_input(); ?>
                    <button class="w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Crear backup</button>
                </form>
                <form action="management/maintenance.php?accion=restore" method="post" class="mt-3 grid gap-3">
                    <?php echo csrf_input(); ?>
                    <select class="field-input" name="backup_name" required>
                        <option value="">Seleccionar backup</option>
                        <?php foreach ($backupFiles as $backupFile): ?>
                            <option value="<?php echo escape_html($backupFile); ?>"><?php echo escape_html($backupFile); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-700" type="submit">Restaurar seleccionado</button>
                </form>
            </section>

            <section id="notificaciones" class="surface-card surface-card-body">
                <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Notificaciones</div>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Cola de envio</h3>
                <form action="management/maintenance.php?accion=dispatch-notifications" method="post" class="mt-5">
                    <?php echo csrf_input(); ?>
                    <button class="w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Procesar cola</button>
                </form>
                <div class="mt-5 max-h-72 space-y-3 overflow-y-auto pr-1">
                    <?php if ($notifications === array()): ?>
                        <?php render_empty_state('Sin notificaciones', 'La cola mostrara confirmaciones, recordatorios y cambios.'); ?>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $notification['template_key']); ?> · <?php echo escape_html((string) $notification['channel']); ?></div>
                                <div class="mt-1 break-words"><?php echo escape_html((string) $notification['recipient']); ?></div>
                                <div class="mt-1"><?php echo escape_html((string) $notification['status']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <section class="surface-card surface-card-body">
            <div class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">Auditoria</div>
            <h3 class="mt-2 text-xl font-semibold text-slate-950">Cambios recientes</h3>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <?php if ($auditEntries === array()): ?>
                    <?php render_empty_state('Sin auditoria', 'Las acciones administrativas quedaran registradas aqui.'); ?>
                <?php else: ?>
                    <?php foreach ($auditEntries as $entry): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            <div class="break-words font-semibold text-slate-950"><?php echo escape_html((string) $entry['summary']); ?></div>
                            <div class="mt-1"><?php echo escape_html((string) $entry['entity_type']); ?> · <?php echo escape_html((string) $entry['action']); ?></div>
                            <div class="mt-1"><?php echo escape_html((string) $entry['created_at']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
