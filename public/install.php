<?php

declare(strict_types=1);

/**
 * One-time, browser-run installer for hosts with no SSH terminal
 * (e.g. Hostinger shared/hPanel). It runs the same setup steps as
 * `php artisan migrate/db:seed/config:cache` would, over HTTP.
 *
 * Protected by INSTALL_TOKEN from .env (constant-time compare) -
 * without it set, this refuses to run at all.
 *
 * DELETE THIS FILE (or at least remove INSTALL_TOKEN from .env) once
 * setup is done. Leaving it reachable is a standing risk: anyone who
 * guesses/leaks the token could re-run migrations/seeders.
 */

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

/** @var \Illuminate\Foundation\Console\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$expected = (string) env('INSTALL_TOKEN', '');
$given = (string) ($_GET['token'] ?? '');

if ($expected === '') {
    http_response_code(403);
    echo "Refused: INSTALL_TOKEN is not set in .env.\n";
    echo "Add a long random value for INSTALL_TOKEN in .env, then reload this page with ?token=<that value>.\n";
    exit;
}

if (! hash_equals($expected, $given)) {
    http_response_code(403);
    echo "Forbidden: missing or incorrect token.\n";
    exit;
}

function run_step(\Illuminate\Contracts\Console\Kernel $kernel, string $command, array $params = []): void
{
    $output = new Symfony\Component\Console\Output\BufferedOutput;
    echo "\n\$ php artisan {$command}\n";

    try {
        $kernel->call($command, $params, $output);
        echo $output->fetch();
    } catch (\Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
    }
}

echo "Link2Social AI - installer\n";
echo str_repeat('=', 40)."\n";

if (! file_exists(__DIR__.'/../.env')) {
    echo "ERROR: .env not found next to this app. Create it from .env.example first, then reload.\n";
    exit;
}

if (env('APP_KEY') === null || env('APP_KEY') === '') {
    run_step($kernel, 'key:generate', ['--force' => true]);
} else {
    echo "\nAPP_KEY already set, skipping key:generate.\n";
}

run_step($kernel, 'storage:link');
run_step($kernel, 'migrate', ['--force' => true]);
run_step($kernel, 'db:seed', ['--class' => 'Database\\Seeders\\SiteContentSeeder', '--force' => true]);
run_step($kernel, 'db:seed', ['--class' => 'Database\\Seeders\\AdminSeeder', '--force' => true]);
run_step($kernel, 'config:cache');
run_step($kernel, 'route:cache');
run_step($kernel, 'view:cache');

echo "\n".str_repeat('=', 40)."\n";
echo "DONE.\n";
echo "1) Visit your site's home page to confirm it loads.\n";
echo "2) Log in at /app/ with your ADMIN_EMAIL / ADMIN_PASSWORD.\n";
echo "3) IMPORTANT: delete public/install.php from the server now (File Manager -> delete),\n";
echo "   or remove INSTALL_TOKEN from .env so this page can no longer run.\n";
