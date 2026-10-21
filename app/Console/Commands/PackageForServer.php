<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * php artisan app:package
 *
 * Builds a zip to upload to the cPanel server (see docs/DEPLOY-CPANEL.md):
 * the app with its production packages, without development files, and
 * without anything in storage, so uploaded photos and files on the server
 * are never overwritten. Run it in Codespaces.
 */
class PackageForServer extends Command
{
    protected $signature = 'app:package {--keep-dev : Skip removing development packages (faster, for testing this command)}';

    protected $description = 'Build a zip of the app to upload to the cPanel server';

    /** Paths left out of the zip (relative to the project folder). */
    private const EXCLUDE = [
        '.git', '.github', '.devcontainer', '.idea', '.vscode', '.phpunit.cache', '.phpunit.result.cache',
        'node_modules', 'tests', 'storage', 'deploy', '.env', '.env.backup',
        'database/database.sqlite', 'public/storage', 'public/hot',
        'bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php', 'bootstrap/cache/services.php',
        'bootstrap/cache/packages.php', 'bootstrap/cache/events.php', 'bootstrap/cache/filament',
    ];

    /** Empty folders the server needs, created by the zip. */
    private const FOLDERS = [
        'storage/app/public', 'storage/app/private', 'storage/framework/cache/data',
        'storage/framework/sessions', 'storage/framework/views', 'storage/logs',
    ];

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('PHP\'s zip extension is missing.');

            return self::FAILURE;
        }

        $base = base_path();
        $target = $base.'/deploy/school-system-'.now()->format('Y-m-d-Hi').'.zip';
        @mkdir(dirname($target), 0775, true);

        if (! $this->option('keep-dev')) {
            $this->components->task('Removing development packages', fn () => $this->composer('install --no-dev --optimize-autoloader --no-interaction'));
        }

        $this->components->task('Publishing Filament files', fn () => $this->callSilently('filament:assets') === 0);

        $count = 0;
        $this->components->task('Building the zip', function () use ($base, $target, &$count): bool {
            $zip = new ZipArchive;

            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

                if ($file->isDir() || $this->excluded($relative)) {
                    continue;
                }

                $zip->addFile($file->getPathname(), $relative);
                $count++;
            }

            foreach (self::FOLDERS as $folder) {
                $zip->addFromString($folder.'/.gitignore', "*\n!.gitignore\n");
            }

            return $zip->close();
        });

        if (! $this->option('keep-dev')) {
            $this->components->task('Restoring development packages', fn () => $this->composer('install --no-interaction'));
        }

        $size = round(filesize($target) / 1048576, 1);
        $this->components->info("Built {$target} ({$count} files, {$size} MB).");
        $this->line('Download it from the deploy folder in the file list, then follow docs/DEPLOY-CPANEL.md.');

        return self::SUCCESS;
    }

    private function excluded(string $relative): bool
    {
        foreach (self::EXCLUDE as $path) {
            if ($relative === $path || str_starts_with($relative, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    private function composer(string $arguments): bool
    {
        return Process::path(base_path())->timeout(900)->run('composer '.$arguments)->successful();
    }
}
