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

function app_favicon_href(?string $configuredAsset = null): string
{
    $configuredAsset = trim((string) $configuredAsset);
    if ($configuredAsset !== '') {
        return 'assets/branding/' . rawurlencode($configuredAsset);
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
    <script src="https://cdn.tailwindcss.com"></script>
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
