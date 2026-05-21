<?php

declare(strict_types=1);

function default_favicon_data_uri(): string
{
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <defs>
    <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="#0f172a"/>
      <stop offset="100%" stop-color="#0f766e"/>
    </linearGradient>
  </defs>
  <rect width="64" height="64" rx="18" fill="url(#g)"/>
  <path d="M19 24h26v5H19zm0 11h18v5H19zm0 11h26v5H19z" fill="#fff"/>
</svg>
SVG;

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function app_relative_prefix(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $directory = trim(dirname($scriptName), '/.');
    if ($directory === '') {
        return '';
    }

    return str_repeat('../', count(array_filter(explode('/', $directory), static fn (string $segment): bool => $segment !== '')));
}

function app_asset_href(string $path): string
{
    return app_relative_prefix() . ltrim($path, '/');
}

function app_favicon_href(?string $configuredAsset = null): string
{
    $configuredAsset = trim((string) $configuredAsset);
    if ($configuredAsset !== '') {
        return app_asset_href('assets/branding/' . rawurlencode($configuredAsset));
    }

    return default_favicon_data_uri();
}

function render_shared_head_assets(array $theme, array $options = array()): void
{
    $favicon = app_favicon_href($options['favicon'] ?? '');
    $useFullCalendar = !empty($options['fullcalendar']);
    $bodyBackground = $options['body_background'] ?? 'linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%)';
    ?>
    <link rel="icon" href="<?php echo escape_html($favicon); ?>">
    <link rel="stylesheet" href="<?php echo escape_html(app_asset_href('assets/styles/app.css')); ?>">
    <?php if ($useFullCalendar): ?>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
    <?php endif; ?>
    <style>
        :root {
            --primary: <?php echo escape_html($theme['primary']); ?>;
            --secondary: <?php echo escape_html($theme['secondary']); ?>;
            --accent: <?php echo escape_html($theme['accent']); ?>;
            --surface: <?php echo escape_html($theme['surface'] ?? '#f8fafc'); ?>;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            background: <?php echo escape_html((string) $bodyBackground); ?>;
        }
        :focus-visible {
            outline: 3px solid color-mix(in srgb, var(--accent) 76%, white);
            outline-offset: 2px;
        }
        dialog::backdrop {
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
        }
        .empty-state {
            border: 1px dashed rgba(148, 163, 184, 0.45);
            background: rgba(248, 250, 252, 0.9);
        }
        .toast-enter {
            animation: toast-in 180ms ease-out;
        }
        .toast-exit {
            opacity: 0;
            transform: translateY(-8px) scale(0.98);
            transition: opacity 180ms ease, transform 180ms ease;
        }
        .fc .fc-toolbar {
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .fc .fc-toolbar-chunk {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .fc .fc-button {
            border-radius: 999px;
        }
        .fc .fc-scroller-harness,
        .fc .fc-scroller {
            overscroll-behavior: contain;
        }
        @media (max-width: 640px) {
            .fc .fc-toolbar {
                align-items: flex-start;
            }
            .fc .fc-toolbar-title {
                font-size: 0.95rem;
                line-height: 1.35;
            }
            .fc .fc-button {
                padding: 0.55rem 0.75rem;
                font-size: 0.8rem;
            }
            .fc .fc-col-header-cell-cushion,
            .fc .fc-daygrid-day-number,
            .fc .fc-list-day-text,
            .fc .fc-list-day-side-text {
                font-size: 0.78rem;
            }
        }
        @keyframes toast-in {
            from { opacity: 0; transform: translateY(-8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
    <?php
}

function render_empty_state(string $title, string $description): void
{
    ?>
    <div class="empty-state rounded-2xl p-5 text-sm text-slate-600">
        <div class="font-semibold text-slate-900"><?php echo escape_html($title); ?></div>
        <p class="mt-2 leading-7"><?php echo escape_html($description); ?></p>
    </div>
    <?php
}

function render_flash_messages(): void
{
    $messages = consume_flash_messages();
    if ($messages === array()) {
        return;
    }
    ?>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 mx-auto flex max-w-2xl flex-col gap-3 px-4">
        <?php foreach ($messages as $message): ?>
            <?php
            $type = (string) ($message['type'] ?? 'info');
            $tone = 'border-slate-200 bg-white text-slate-900';
            if ($type === 'success') {
                $tone = 'border-emerald-200 bg-emerald-50 text-emerald-900';
            } elseif ($type === 'error') {
                $tone = 'border-rose-200 bg-rose-50 text-rose-900';
            }
            ?>
            <div class="toast-enter pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-xl <?php echo escape_html($tone); ?>" data-toast role="status" aria-live="polite">
                <div class="min-w-0 flex-1 break-words">
                    <?php echo escape_html((string) ($message['message'] ?? '')); ?>
                </div>
                <button class="shrink-0 rounded-full border border-current/15 px-2 py-0.5 text-sm font-semibold opacity-70 transition hover:opacity-100 focus:opacity-100" type="button" data-toast-close aria-label="Cerrar mensaje">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        (() => {
            const dismissToast = (toast) => {
                if (!toast || toast.dataset.closing === '1') {
                    return;
                }
                toast.dataset.closing = '1';
                toast.classList.add('toast-exit');
                window.setTimeout(() => toast.remove(), 220);
            };

            document.querySelectorAll('[data-toast]').forEach((toast) => {
                let timeoutId = window.setTimeout(() => dismissToast(toast), 5200);
                toast.addEventListener('mouseenter', () => window.clearTimeout(timeoutId));
                toast.addEventListener('focusin', () => window.clearTimeout(timeoutId));
                toast.addEventListener('mouseleave', () => {
                    timeoutId = window.setTimeout(() => dismissToast(toast), 1800);
                });
                toast.addEventListener('focusout', () => {
                    timeoutId = window.setTimeout(() => dismissToast(toast), 1800);
                });
                toast.querySelector('[data-toast-close]')?.addEventListener('click', () => dismissToast(toast));
            });

            window.appToast = (message, type = 'info') => {
                const tones = {
                    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
                    error: 'border-rose-200 bg-rose-50 text-rose-900',
                    info: 'border-slate-200 bg-white text-slate-900'
                };
                let stack = document.querySelector('[data-toast-stack]');
                if (!stack) {
                    stack = document.createElement('div');
                    stack.dataset.toastStack = '1';
                    stack.className = 'pointer-events-none fixed inset-x-0 top-4 z-50 mx-auto flex max-w-2xl flex-col gap-3 px-4';
                    document.body.appendChild(stack);
                }
                const toast = document.createElement('div');
                toast.className = `toast-enter pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-xl ${tones[type] || tones.info}`;
                toast.setAttribute('role', 'status');
                toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
                toast.innerHTML = `<div class="min-w-0 flex-1 break-words"></div><button class="shrink-0 rounded-full border border-current/15 px-2 py-0.5 text-sm font-semibold opacity-70 transition hover:opacity-100 focus:opacity-100" type="button" aria-label="Cerrar mensaje">&times;</button>`;
                toast.firstElementChild.textContent = message;
                toast.querySelector('button')?.addEventListener('click', () => dismissToast(toast));
                stack.appendChild(toast);
                window.setTimeout(() => dismissToast(toast), type === 'error' ? 7200 : 4600);
            };

            window.appConfirm = (message) => new Promise((resolve) => {
                const dialog = document.createElement('dialog');
                dialog.className = 'dialog-shell';
                dialog.innerHTML = `
                    <div class="dialog-body">
                        <div class="section-eyebrow">Confirmacion</div>
                        <h3 class="mt-2 text-2xl font-semibold text-slate-950">Revisar accion</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600"></p>
                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button class="btn-secondary" type="button" data-cancel>Volver</button>
                            <button class="btn-danger-soft" type="button" data-confirm>Confirmar</button>
                        </div>
                    </div>`;
                dialog.querySelector('p').textContent = message;
                document.body.appendChild(dialog);
                const close = (value) => {
                    resolve(value);
                    dialog.close();
                    window.setTimeout(() => dialog.remove(), 120);
                };
                dialog.querySelector('[data-cancel]')?.addEventListener('click', () => close(false));
                dialog.querySelector('[data-confirm]')?.addEventListener('click', () => close(true));
                dialog.addEventListener('cancel', (event) => {
                    event.preventDefault();
                    close(false);
                });
                dialog.showModal();
            });
        })();
    </script>
    <?php
}
