<?php
/**
 * ParkHub — Web Installation Wizard
 *
 * Single-file installer for shared hosting environments.
 * Bootstraps Laravel to run migrations and create the admin user.
 *
 * IMPORTANT: Delete this file after installation is complete!
 */

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// ---------------------------------------------------------------------------
// 0. Safety: refuse to run if already installed
// ---------------------------------------------------------------------------

$basePath = dirname(__DIR__);
$envPath = $basePath.'/.env';

function isAlreadyInstalled(string $basePath, string $envPath): bool
{
    if (! file_exists($envPath)) {
        return false;
    }
    $env = file_get_contents($envPath);
    if (! preg_match('/^APP_KEY=base64:.+$/m', $env)) {
        return false;
    }
    // Check if the database has users — means setup already ran
    try {
        $lines = parseEnvFile($envPath);
        $driver = $lines['DB_CONNECTION'] ?? 'sqlite';
        if ($driver === 'sqlite') {
            $dbPath = $basePath.'/database/database.sqlite';
            if (! file_exists($dbPath)) {
                return false;
            }
            $pdo = new PDO("sqlite:{$dbPath}");
        } else {
            $host = $lines['DB_HOST'] ?? '127.0.0.1';
            $port = $lines['DB_PORT'] ?? '3306';
            $db = $lines['DB_DATABASE'] ?? 'parkhub';
            $user = $lines['DB_USERNAME'] ?? 'root';
            $pass = $lines['DB_PASSWORD'] ?? '';
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');

        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        // If .env exists and has an APP_KEY, assume installed (safe default).
        // Only return false if there's genuinely no .env or no key yet.
        $envPath = dirname(__DIR__).'/.env';
        if (file_exists($envPath)) {
            $env = parseEnvFile($envPath);
            if (! empty($env['APP_KEY'])) {
                return true;
            }
        }

        return false;
    }
}

function parseEnvFile(string $path): array
{
    $lines = [];
    if (! file_exists($path)) {
        return $lines;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            // Strip surrounding quotes
            if (preg_match('/^"(.*)"$/', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
                $value = $m[1];
            }
            $lines[trim($key)] = $value;
        }
    }

    return $lines;
}

if (isAlreadyInstalled($basePath, $envPath)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>ParkHub — Already Installed</title>';
    echo '<style>body{background:#111;color:#e5e7eb;font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}';
    echo '.box{background:#1f2937;padding:2.5rem;border-radius:1rem;max-width:500px;text-align:center;border:1px solid #374151}';
    echo 'h1{color:#d97706;margin-top:0}p{line-height:1.6}code{background:#374151;padding:.2em .5em;border-radius:.3em;font-size:.9em}</style></head>';
    echo '<body><div class="box"><h1>Already Installed</h1>';
    echo '<p>ParkHub is already configured and has users in the database.</p>';
    echo '<p>For security, please <strong>delete this file</strong>:<br><code>public/install.php</code></p>';
    echo '</div></body></html>';
    exit;
}

// ---------------------------------------------------------------------------
// 1. Session + CSRF
// ---------------------------------------------------------------------------

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
}

// Rate limiting: max 10 POST requests per minute
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['post_times'] = array_filter(
        $_SESSION['post_times'] ?? [],
        fn ($t) => $t > time() - 60
    );
    if (count($_SESSION['post_times']) >= 10) {
        $error = 'Too many requests. Please wait a moment and try again.';
    } else {
        $_SESSION['post_times'][] = time();
    }

    // CSRF check
    if (! isset($error) && (! isset($_POST['_token']) || ! hash_equals($_SESSION['csrf_token'], $_POST['_token']))) {
        $error = 'Invalid CSRF token. Please reload and try again.';
    }
}

$csrf = $_SESSION['csrf_token'];
$step = (int) ($_SESSION['install_step'] ?? 1);

// ---------------------------------------------------------------------------
// 2. Requirements check helpers
// ---------------------------------------------------------------------------

function checkRequirements(string $basePath): array
{
    $results = [];

    // PHP version
    $results[] = [
        'label' => 'PHP >= 8.2',
        'detail' => 'Current: '.PHP_VERSION,
        'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'required' => true,
    ];

    // Extensions
    $required = ['pdo', 'json', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'bcmath'];
    foreach ($required as $ext) {
        $results[] = [
            'label' => "ext-{$ext}",
            'detail' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            'ok' => extension_loaded($ext),
            'required' => true,
        ];
    }

    // PDO driver — need at least one of mysql or sqlite
    $hasMysql = extension_loaded('pdo_mysql');
    $hasSqlite = extension_loaded('pdo_sqlite');
    $results[] = [
        'label' => 'pdo_mysql or pdo_sqlite',
        'detail' => implode(', ', array_filter([
            $hasMysql ? 'pdo_mysql' : null,
            $hasSqlite ? 'pdo_sqlite' : null,
        ])) ?: 'None',
        'ok' => $hasMysql || $hasSqlite,
        'required' => true,
    ];

    // Writable directories
    foreach (['storage', 'bootstrap/cache'] as $dir) {
        $path = $basePath.'/'.$dir;
        $writable = is_dir($path) && is_writable($path);
        $results[] = [
            'label' => "{$dir}/ writable",
            'detail' => $writable ? 'Writable' : 'Not writable',
            'ok' => $writable,
            'required' => true,
        ];
    }

    // vendor/autoload.php exists
    $hasVendor = file_exists($basePath.'/vendor/autoload.php');
    $results[] = [
        'label' => 'vendor/autoload.php',
        'detail' => $hasVendor ? 'Found' : 'Missing — run composer install',
        'ok' => $hasVendor,
        'required' => true,
    ];

    return $results;
}

// ---------------------------------------------------------------------------
// 3. Handle POST actions
// ---------------------------------------------------------------------------

$formData = $_SESSION['form_data'] ?? [];
$success = '';
$error = $error ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $error) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        // Step 1 → 2: requirements passed
        case 'check_requirements':
            $checks = checkRequirements($basePath);
            $allOk = ! in_array(false, array_column(
                array_filter($checks, fn ($c) => $c['required']),
                'ok'
            ), true);
            if ($allOk) {
                $_SESSION['install_step'] = 2;
                $step = 2;
            } else {
                $error = 'Not all requirements are met. Please fix the issues above before continuing.';
            }
            break;

            // Step 2 → 3: database configuration
        case 'configure_database':
            $dbDriver = $_POST['db_driver'] ?? 'sqlite';
            $formData['db_driver'] = $dbDriver;

            if ($dbDriver === 'mysql') {
                $formData['db_host'] = trim($_POST['db_host'] ?? '127.0.0.1');
                $formData['db_port'] = trim($_POST['db_port'] ?? '3306');
                $formData['db_database'] = trim($_POST['db_database'] ?? 'parkhub');
                $formData['db_username'] = trim($_POST['db_username'] ?? '');
                $formData['db_password'] = $_POST['db_password'] ?? '';

                if (! $formData['db_host'] || ! $formData['db_database'] || ! $formData['db_username']) {
                    $error = 'Host, database name, and username are required for MySQL.';
                    break;
                }

                // Test connection
                try {
                    $dsn = "mysql:host={$formData['db_host']};port={$formData['db_port']};dbname={$formData['db_database']}";
                    $pdo = new PDO($dsn, $formData['db_username'], $formData['db_password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5,
                    ]);
                    unset($pdo);
                } catch (PDOException $e) {
                    $error = 'MySQL connection failed: '.htmlspecialchars($e->getMessage());
                    break;
                }
            } else {
                // SQLite: ensure database directory is writable
                $dbDir = $basePath.'/database';
                if (! is_writable($dbDir)) {
                    $error = 'The database/ directory is not writable. SQLite cannot create its file.';
                    break;
                }
            }

            $_SESSION['form_data'] = $formData;
            $_SESSION['install_step'] = 3;
            $step = 3;
            break;

            // Step 3 → 4: admin account
        case 'configure_admin':
            $formData['admin_email'] = trim($_POST['admin_email'] ?? '');
            $formData['admin_username'] = trim($_POST['admin_username'] ?? 'admin');
            $formData['admin_password'] = $_POST['admin_password'] ?? '';
            $confirmPassword = $_POST['admin_password_confirm'] ?? '';

            if (! filter_var($formData['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
                break;
            }
            if (strlen($formData['admin_username']) < 2) {
                $error = 'Username must be at least 2 characters.';
                break;
            }
            if (strlen($formData['admin_password']) < 8) {
                $error = 'Password must be at least 8 characters.';
                break;
            }
            if ($formData['admin_password'] !== $confirmPassword) {
                $error = 'Passwords do not match.';
                break;
            }

            $_SESSION['form_data'] = $formData;
            $_SESSION['install_step'] = 4;
            $step = 4;
            break;

            // Step 4 → 5: write .env, run migrations, create admin
        case 'run_install':
            $formData['app_url'] = rtrim(trim($_POST['app_url'] ?? ''), '/');
            if (! filter_var($formData['app_url'], FILTER_VALIDATE_URL)) {
                $error = 'Please enter a valid application URL.';
                break;
            }
            $_SESSION['form_data'] = $formData;

            // Generate APP_KEY
            $appKey = 'base64:'.base64_encode(random_bytes(32));

            // Build .env content
            $envLines = [
                'APP_NAME=ParkHub',
                'APP_ENV=production',
                "APP_KEY={$appKey}",
                'APP_DEBUG=false',
                "APP_URL={$formData['app_url']}",
                '',
                'APP_LOCALE=en',
                'APP_FALLBACK_LOCALE=en',
                'APP_FAKER_LOCALE=en_US',
                '',
                'APP_MAINTENANCE_DRIVER=file',
                '',
                'BCRYPT_ROUNDS=12',
                '',
                'LOG_CHANNEL=stack',
                'LOG_STACK=single',
                'LOG_DEPRECATIONS_CHANNEL=null',
                'LOG_LEVEL=warning',
                '',
                "PARKHUB_ADMIN_EMAIL={$formData['admin_email']}",
                '',
            ];

            if (($formData['db_driver'] ?? 'sqlite') === 'sqlite') {
                $envLines[] = 'DB_CONNECTION=sqlite';
            } else {
                $envLines[] = 'DB_CONNECTION=mysql';
                $envLines[] = "DB_HOST={$formData['db_host']}";
                $envLines[] = "DB_PORT={$formData['db_port']}";
                $envLines[] = "DB_DATABASE={$formData['db_database']}";
                $envLines[] = "DB_USERNAME={$formData['db_username']}";
                $dbPass = str_replace('"', '\\"', $formData['db_password']);
                $envLines[] = "DB_PASSWORD=\"{$dbPass}\"";
            }

            $envLines = array_merge($envLines, [
                '',
                'SESSION_DRIVER=database',
                'SESSION_LIFETIME=120',
                'SESSION_ENCRYPT=false',
                'SESSION_PATH=/',
                'SESSION_DOMAIN=null',
                '',
                'BROADCAST_CONNECTION=log',
                'FILESYSTEM_DISK=local',
                'QUEUE_CONNECTION=database',
                '',
                'CACHE_STORE=database',
                '',
                'MAIL_MAILER=log',
                'MAIL_SCHEME=null',
                'MAIL_HOST=smtp.example.com',
                'MAIL_PORT=587',
                'MAIL_USERNAME=null',
                'MAIL_PASSWORD=null',
                'MAIL_FROM_ADDRESS="noreply@example.com"',
                'MAIL_FROM_NAME="${APP_NAME}"',
                '',
                'VITE_APP_NAME="${APP_NAME}"',
            ]);

            $envContent = implode("\n", $envLines)."\n";

            // Write .env
            if (file_put_contents($envPath, $envContent) === false) {
                $error = 'Failed to write .env file. Check directory permissions.';
                break;
            }

            // For SQLite, create the database file
            if (($formData['db_driver'] ?? 'sqlite') === 'sqlite') {
                $sqlitePath = $basePath.'/database/database.sqlite';
                if (! file_exists($sqlitePath)) {
                    if (file_put_contents($sqlitePath, '') === false) {
                        $error = 'Failed to create SQLite database file.';
                        break;
                    }
                }
            }

            // Bootstrap Laravel
            try {
                // Clear any cached config/env so Laravel reads the fresh .env
                foreach (['config.php', 'services.php', 'routes-v7.php'] as $cacheFile) {
                    $cachePath = $basePath.'/bootstrap/cache/'.$cacheFile;
                    if (file_exists($cachePath)) {
                        @unlink($cachePath);
                    }
                }

                require $basePath.'/vendor/autoload.php';

                $app = require $basePath.'/bootstrap/app.php';
                $kernel = $app->make(Kernel::class);
                $kernel->bootstrap();

                // Run migrations
                $migrateResult = Artisan::call('migrate', [
                    '--force' => true,
                    '--no-interaction' => true,
                ]);

                if ($migrateResult !== 0) {
                    $migrateOutput = Artisan::output();
                    $error = 'Migration failed (exit code '.$migrateResult.'): '.htmlspecialchars($migrateOutput);
                    break;
                }

                // Create admin user
                $adminResult = Artisan::call('parkhub:create-admin', [
                    '--email' => $formData['admin_email'],
                    '--password' => $formData['admin_password'],
                    '--username' => $formData['admin_username'],
                ]);

                if ($adminResult !== 0) {
                    $adminOutput = Artisan::output();
                    $error = 'Admin creation failed: '.htmlspecialchars($adminOutput);
                    break;
                }

                // Clear password from .env after successful admin creation
                $cleanEnv = preg_replace(
                    '/^PARKHUB_ADMIN_PASSWORD=.*$/m',
                    'PARKHUB_ADMIN_PASSWORD=',
                    file_get_contents($envPath)
                );
                file_put_contents($envPath, $cleanEnv);

            } catch (Throwable $e) {
                $error = 'Installation error: '.htmlspecialchars($e->getMessage());
                break;
            }

            $_SESSION['install_step'] = 5;
            $step = 5;
            break;

            // Step 5: self-delete
        case 'self_delete':
            if (@unlink(__FILE__)) {
                session_destroy();
                header('Location: '.($formData['app_url'] ?? '/'));
                exit;
            } else {
                $error = 'Could not auto-delete install.php. Please delete it manually.';
            }
            break;
    }
}

// Re-check requirements for display on step 1
$checks = ($step === 1) ? checkRequirements($basePath) : [];

// Auto-detect app URL
$autoUrl = '';
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $autoUrl = 'https://';
} else {
    $autoUrl = 'http://';
}
$autoUrl .= $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
// The app root is one level above /public
if ($scriptDir !== '/' && basename($scriptDir) === 'public') {
    $autoUrl .= dirname($scriptDir);
} elseif ($scriptDir !== '/') {
    $autoUrl .= $scriptDir;
}
$autoUrl = rtrim($autoUrl, '/');

// Step labels
$steps = [
    1 => 'Requirements',
    2 => 'Database',
    3 => 'Admin Account',
    4 => 'Install',
    5 => 'Complete',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ParkHub &mdash; Installation</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
            line-height: 1.6;
        }
        a { color: #d97706; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .container {
            width: 100%;
            max-width: 640px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: .25rem;
        }
        .header h1 span { color: #d97706; }
        .header p {
            color: #94a3b8;
            font-size: .95rem;
        }

        /* Stepper */
        .stepper {
            display: flex;
            justify-content: center;
            gap: .5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .stepper-item {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .8rem;
            color: #475569;
        }
        .stepper-item.active { color: #d97706; font-weight: 600; }
        .stepper-item.done { color: #22c55e; }
        .stepper-dot {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 700;
            background: #1e293b;
            border: 2px solid #334155;
            flex-shrink: 0;
        }
        .stepper-item.active .stepper-dot { border-color: #d97706; background: #451a03; color: #d97706; }
        .stepper-item.done .stepper-dot { border-color: #22c55e; background: #052e16; color: #22c55e; }
        .stepper-sep { width: 1.5rem; height: 2px; background: #334155; align-self: center; }

        /* Card */
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: .75rem;
            padding: 2rem;
        }
        .card h2 {
            font-size: 1.25rem;
            color: #f8fafc;
            margin-bottom: 1.25rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid #334155;
        }

        /* Alerts */
        .alert {
            padding: .875rem 1rem;
            border-radius: .5rem;
            margin-bottom: 1.25rem;
            font-size: .9rem;
            line-height: 1.5;
        }
        .alert-error { background: #450a0a; border: 1px solid #7f1d1d; color: #fca5a5; }
        .alert-success { background: #052e16; border: 1px solid #14532d; color: #86efac; }
        .alert-warning { background: #451a03; border: 1px solid #78350f; color: #fcd34d; }

        /* Check list */
        .check-list { list-style: none; }
        .check-list li {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .5rem 0;
            border-bottom: 1px solid #1e293b;
            font-size: .9rem;
        }
        .check-list li:last-child { border-bottom: none; }
        .check-icon {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .check-icon.ok { background: #052e16; color: #22c55e; border: 1px solid #14532d; }
        .check-icon.fail { background: #450a0a; color: #ef4444; border: 1px solid #7f1d1d; }
        .check-detail { color: #64748b; margin-left: auto; font-size: .8rem; }

        /* Form */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: .35rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: .625rem .75rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: .5rem;
            color: #e2e8f0;
            font-size: .9rem;
            font-family: inherit;
            transition: border-color .15s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #d97706;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, .15);
        }
        .form-group .hint {
            font-size: .8rem;
            color: #64748b;
            margin-top: .3rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 500px) {
            .form-row { grid-template-columns: 1fr; }
        }

        /* Radio group */
        .radio-group {
            display: flex;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .radio-option {
            flex: 1;
            position: relative;
        }
        .radio-option input { display: none; }
        .radio-option label {
            display: block;
            padding: .75rem 1rem;
            background: #0f172a;
            border: 2px solid #334155;
            border-radius: .5rem;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: .9rem;
            transition: border-color .15s, background .15s;
        }
        .radio-option input:checked + label {
            border-color: #d97706;
            background: #451a03;
            color: #fbbf24;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: .7rem 1.5rem;
            border: none;
            border-radius: .5rem;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s, transform .1s;
        }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: #d97706; color: #fff; }
        .btn-primary:hover { background: #b45309; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-group {
            display: flex;
            gap: .75rem;
            margin-top: 1.5rem;
        }

        #mysql-fields { display: none; }
        #mysql-fields.visible { display: block; }

        /* Summary table */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        .summary-table td {
            padding: .5rem .75rem;
            border-bottom: 1px solid #334155;
            font-size: .9rem;
        }
        .summary-table td:first-child {
            color: #94a3b8;
            font-weight: 600;
            width: 40%;
        }

        /* Success page */
        .success-icon {
            width: 4rem;
            height: 4rem;
            background: #052e16;
            border: 2px solid #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.25rem;
            color: #22c55e;
        }

        .code-block {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: .5rem;
            padding: .75rem 1rem;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: .85rem;
            color: #fbbf24;
            word-break: break-all;
            margin: .75rem 0;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            color: #475569;
            font-size: .8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Park<span>Hub</span></h1>
            <p>Installation Wizard</p>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <?php foreach ($steps as $num => $label) { ?>
                <?php if ($num > 1) { ?><div class="stepper-sep"></div><?php } ?>
                <div class="stepper-item <?= $num < $step ? 'done' : ($num === $step ? 'active' : '') ?>">
                    <div class="stepper-dot">
                        <?php if ($num < $step) { ?>&#10003;<?php } else { ?><?= $num ?><?php } ?>
                    </div>
                    <span><?= $label ?></span>
                </div>
            <?php } ?>
        </div>

        <!-- Main card -->
        <div class="card">
            <?php if ($error) { ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php } ?>

            <?php if ($step === 1) { ?>
            <!-- ==================== STEP 1: Requirements ==================== -->
            <h2>System Requirements</h2>
            <ul class="check-list">
                <?php foreach ($checks as $c) { ?>
                <li>
                    <span class="check-icon <?= $c['ok'] ? 'ok' : 'fail' ?>">
                        <?= $c['ok'] ? '&#10003;' : '&#10007;' ?>
                    </span>
                    <span><?= htmlspecialchars($c['label']) ?></span>
                    <span class="check-detail"><?= htmlspecialchars($c['detail']) ?></span>
                </li>
                <?php } ?>
            </ul>
            <form method="post">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="check_requirements">
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </form>

            <?php } elseif ($step === 2) { ?>
            <!-- ==================== STEP 2: Database ==================== -->
            <h2>Database Configuration</h2>
            <form method="post">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="configure_database">
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="db_driver" id="db_sqlite" value="sqlite" <?= ($formData['db_driver'] ?? 'sqlite') === 'sqlite' ? 'checked' : '' ?>>
                        <label for="db_sqlite">SQLite</label>
                    </div>
                    <?php if (extension_loaded('pdo_mysql')) { ?>
                    <div class="radio-option">
                        <input type="radio" name="db_driver" id="db_mysql" value="mysql" <?= ($formData['db_driver'] ?? '') === 'mysql' ? 'checked' : '' ?>>
                        <label for="db_mysql">MySQL</label>
                    </div>
                    <?php } ?>
                </div>
                <div id="sqlite-info" class="alert alert-success">
                    SQLite requires no configuration. A database file will be created automatically.
                </div>
                <div id="mysql-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_host">Host</label>
                            <input type="text" name="db_host" id="db_host" value="<?= htmlspecialchars($formData['db_host'] ?? '127.0.0.1') ?>">
                        </div>
                        <div class="form-group">
                            <label for="db_port">Port</label>
                            <input type="text" name="db_port" id="db_port" value="<?= htmlspecialchars($formData['db_port'] ?? '3306') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="db_database">Database Name</label>
                        <input type="text" name="db_database" id="db_database" value="<?= htmlspecialchars($formData['db_database'] ?? 'parkhub') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_username">Username</label>
                            <input type="text" name="db_username" id="db_username" value="<?= htmlspecialchars($formData['db_username'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="db_password">Password</label>
                            <input type="password" name="db_password" id="db_password" value="<?= htmlspecialchars($formData['db_password'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Test &amp; Continue</button>
                </div>
            </form>
            <script>
                (function() {
                    const radios = document.querySelectorAll('input[name="db_driver"]');
                    const mysqlFields = document.getElementById('mysql-fields');
                    const sqliteInfo = document.getElementById('sqlite-info');
                    function toggle() {
                        const v = document.querySelector('input[name="db_driver"]:checked').value;
                        mysqlFields.className = v === 'mysql' ? 'visible' : '';
                        sqliteInfo.style.display = v === 'sqlite' ? '' : 'none';
                    }
                    radios.forEach(r => r.addEventListener('change', toggle));
                    toggle();
                })();
            </script>

            <?php } elseif ($step === 3) { ?>
            <!-- ==================== STEP 3: Admin Account ==================== -->
            <h2>Admin Account</h2>
            <form method="post">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="configure_admin">
                <div class="form-group">
                    <label for="admin_email">Email</label>
                    <input type="email" name="admin_email" id="admin_email" value="<?= htmlspecialchars($formData['admin_email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_username">Username</label>
                    <input type="text" name="admin_username" id="admin_username" value="<?= htmlspecialchars($formData['admin_username'] ?? 'admin') ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Password</label>
                    <input type="password" name="admin_password" id="admin_password" minlength="8" required>
                    <div class="hint">Minimum 8 characters</div>
                </div>
                <div class="form-group">
                    <label for="admin_password_confirm">Confirm Password</label>
                    <input type="password" name="admin_password_confirm" id="admin_password_confirm" minlength="8" required>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </form>

            <?php } elseif ($step === 4) { ?>
            <!-- ==================== STEP 4: Review & Install ==================== -->
            <h2>Review &amp; Install</h2>
            <p style="margin-bottom:1rem;color:#94a3b8;font-size:.9rem;">
                Review your settings below. Clicking <strong>Install Now</strong> will write the
                configuration, run database migrations, and create your admin account.
            </p>
            <table class="summary-table">
                <tr><td>App URL</td><td><input form="install-form" type="text" name="app_url" value="<?= htmlspecialchars($formData['app_url'] ?? $autoUrl) ?>" style="width:100%;padding:.4rem .6rem;background:#0f172a;border:1px solid #334155;border-radius:.35rem;color:#e2e8f0;font-size:.9rem;font-family:inherit;"></td></tr>
                <tr><td>Database</td><td><?= htmlspecialchars(($formData['db_driver'] ?? 'sqlite') === 'sqlite' ? 'SQLite' : 'MySQL ('.($formData['db_host'] ?? '').'/'.($formData['db_database'] ?? '').')') ?></td></tr>
                <tr><td>Admin Email</td><td><?= htmlspecialchars($formData['admin_email'] ?? '') ?></td></tr>
                <tr><td>Admin Username</td><td><?= htmlspecialchars($formData['admin_username'] ?? 'admin') ?></td></tr>
            </table>
            <form method="post" id="install-form">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="run_install">
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" onclick="this.disabled=true;this.textContent='Installing...';this.form.submit();">Install Now</button>
                </div>
            </form>

            <?php } elseif ($step === 5) { ?>
            <!-- ==================== STEP 5: Success ==================== -->
            <div style="text-align:center">
                <div class="success-icon">&#10003;</div>
                <h2 style="border:none;text-align:center;padding:0;margin-bottom:.5rem;">Installation Complete!</h2>
                <p style="color:#94a3b8;margin-bottom:1.5rem;">ParkHub has been successfully installed.</p>
            </div>
            <div class="alert alert-success">
                <strong>Login URL:</strong>
                <div class="code-block"><?= htmlspecialchars(($formData['app_url'] ?? $autoUrl).'/login') ?></div>
                <strong>Admin:</strong> <?= htmlspecialchars($formData['admin_email'] ?? '') ?>
            </div>
            <div class="alert alert-warning">
                <strong>Security Warning:</strong> You must delete <code>install.php</code> immediately
                to prevent unauthorized re-installation. Click the button below or delete the file manually.
            </div>
            <form method="post">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="self_delete">
                <div class="btn-group" style="justify-content:center">
                    <button type="submit" class="btn btn-danger">Delete install.php &amp; Go to ParkHub</button>
                    <a href="<?= htmlspecialchars($formData['app_url'] ?? '/') ?>" class="btn btn-primary">Go to ParkHub</a>
                </div>
            </form>
            <?php } ?>
        </div>

        <div class="footer">
            ParkHub Installation Wizard &middot; PHP <?= PHP_VERSION ?>
        </div>
    </div>
</body>
</html>
