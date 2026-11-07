<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegarde : base de données + photos/factures, dans un zip daté.
 * Copie aussi le zip dans le dossier « backup_path » des paramètres (clé USB, Google Drive pour ordinateur…).
 *
 *   php artisan app:backup
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup {--no-copy : Ne pas copier vers le dossier externe}';

    protected $description = 'Sauvegarde la base de données et les fichiers dans storage/app/backups';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Y-m-d_His');
        $sql = "{$dir}/base_{$stamp}.sql";

        try {
            $this->dumpDatabase($sql);
        } catch (RuntimeException $e) {
            $this->error('Échec de la sauvegarde : '.$e->getMessage());

            return self::FAILURE;
        }

        $zipPath = "{$dir}/sauvegarde_{$stamp}.zip";
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sql, basename($sql));
        $public = storage_path('app/public');
        if (is_dir($public)) {
            foreach (File::allFiles($public) as $file) {
                $zip->addFile($file->getPathname(), 'fichiers/'.$file->getRelativePathname());
            }
        }
        $zip->close();
        File::delete($sql);

        $this->info('Sauvegarde créée : '.$zipPath.' ('.round(filesize($zipPath) / 1024).' Ko)');

        // Copie externe
        $external = Setting::get('backup_path');
        if ($external && ! $this->option('no-copy')) {
            if (is_dir($external) && is_writable($external)) {
                File::copy($zipPath, rtrim($external, '\\/').DIRECTORY_SEPARATOR.basename($zipPath));
                $this->info("Copie envoyée vers {$external}");
            } else {
                $this->warn("Dossier externe introuvable ou protégé : {$external} (clé USB débranchée ?)");
            }
        }

        // Rotation : on garde les N plus récentes
        $keep = (int) Setting::get('backup_keep', 30);
        collect(File::files($dir))->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
            ->sortByDesc(fn ($f) => $f->getMTime())->slice($keep)->each(fn ($f) => File::delete($f->getPathname()));

        return self::SUCCESS;
    }

    private function dumpDatabase(string $target): void
    {
        $conn = config('database.default');
        $cfg = config("database.connections.{$conn}");

        if ($cfg['driver'] === 'sqlite') {
            File::copy($cfg['database'], $target);

            return;
        }

        if (! in_array($cfg['driver'], ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Pilote {$cfg['driver']} non géré.");
        }

        // Laragon : mysqldump est dans C:\laragon\bin\mysql\mysql-x.y\bin — chemin réglé par MYSQLDUMP_PATH (.env → config/services.php)
        $binary = config('services.mysqldump') ?: 'mysqldump';
        $result = Process::timeout(600)->env(['MYSQL_PWD' => (string) $cfg['password']])->run([
            $binary, '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4',
            '--result-file='.$target, $cfg['database'],
        ]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'mysqldump a échoué');
        }
    }
}
