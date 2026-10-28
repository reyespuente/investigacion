<?php

return [
    'domains' => array_values(array_filter(array_map(fn ($v) => strtolower(trim($v)), explode(',', env('ALLOWED_EMAIL_DOMAINS', 'example.edu'))))),
    'institution' => env('INSTITUTION_NAME', 'Tu institución'), 'short_name' => env('INSTITUTION_SHORT_NAME', 'Institución'), 'portal_name' => env('PORTAL_NAME', 'Repositorio académico'),
    'network_name' => env('INSTITUTION_NETWORK', ''), 'institution_logo' => env('INSTITUTION_LOGO', ''), 'network_logo' => env('NETWORK_LOGO', ''),
    'primary_color' => env('BRAND_PRIMARY', '#1B396A'), 'accent_color' => env('BRAND_ACCENT', '#117533'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.edu'), 'admin_name' => env('ADMIN_NAME', 'Administrador'), 'admin_password' => env('ADMIN_PASSWORD'),
    'privacy_url' => env('INSTITUTION_PRIVACY_URL', ''),
    'privacy_contact' => env('PRIVACY_CONTACT', ''),
    'turnstile_enabled' => env('TURNSTILE_ENABLED', true), 'turnstile_site_key' => env('TURNSTILE_SITE_KEY'), 'turnstile_secret_key' => env('TURNSTILE_SECRET_KEY'),
    'turnstile_hostname' => env('TURNSTILE_HOSTNAME', 'localhost'),
    'types' => ['article' => 'Artículo', 'review' => 'Revisión', 'conference-paper' => 'Congreso', 'book-chapter' => 'Capítulo de libro', 'book' => 'Libro', 'preprint' => 'Preprint', 'practice-report' => 'Reporte de práctica', 'report' => 'Informe', 'dissertation' => 'Tesis', 'poster' => 'Póster', 'presentation' => 'Presentación', 'other' => 'Otro'],
    'export_limit' => 5000,
];
