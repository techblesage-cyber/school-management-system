<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * php artisan app:install
 *
 * Run after uploading a new version to the server: updates the database,
 * links the storage folder and caches the configuration. Safe to run again.
 * On cPanel without SSH, run it once from Cron Jobs (see docs/DEPLOY-CPANEL.md).
 */
class InstallOrUpdate extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Update the database, link storage and cache settings after uploading a new version';

    public function handle(): int
    {
        if (blank(config('app.key'))) {
            $this->error('APP_KEY is missing from .env. Generate one with "php artisan key:generate --show" and paste it in.');

            return self::FAILURE;
        }

        $this->components->task('Updating the database', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);

        if (! file_exists(public_path('storage'))) {
            $linked = false;

            $this->components->task('Linking public storage', function () use (&$linked): bool {
                $linked = $this->callSilently('storage:link') === 0 && file_exists(public_path('storage'));

                // Some hosts don't allow links: copy what's there so far instead
                return $linked || File::copyDirectory(storage_path('app/public'), public_path('storage'));
            });

            if (! $linked) {
                $this->components->warn('This server does not allow storage links. So that new photos and logos show, add this line to .env and run app:install again:');
                $this->line('    PUBLIC_DISK_ROOT='.public_path('storage'));
            }
        }

        $this->components->task('Publishing Filament files', fn () => $this->callSilently('filament:assets') === 0);
        $this->components->task('Caching settings, routes and views', fn () => $this->callSilently('optimize') === 0);

        $this->components->info('Done. The system is up to date.');

        return self::SUCCESS;
    }
}
