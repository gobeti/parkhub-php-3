<?php

// Build allowed origins: always include APP_URL, local dev, plus any extras from env
$baseOrigins = array_filter([
    env('APP_URL'),                   // The actual deployed URL (e.g. https://parkhub-php-demo.onrender.com)
    'https://parkhub-php.test',       // Local K8s dev
    'http://localhost',
    'http://localhost:5173',          // Vite dev server
    'http://127.0.0.1',
    'http://127.0.0.1:5173',
]);

// Additional origins from env (comma-separated, e.g. "https://myapp.com,https://staging.myapp.com")
$extraOrigins = env('APP_EXTRA_ALLOWED_ORIGINS')
    ? array_map('trim', explode(',', env('APP_EXTRA_ALLOWED_ORIGINS')))
    : [];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_merge($baseOrigins, $extraOrigins))),
    // Specific origin patterns can be added via APP_EXTRA_ALLOWED_ORIGINS env var
    // (comma-separated). Do NOT use wildcard patterns for PaaS provider domains
    // as they would allow any tenant on those platforms to make cross-origin requests.
    'allowed_origins_patterns' => array_filter([
        // Allow demos page on GitHub Pages
        '^https://nash87\.github\.io$',
    ]),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
