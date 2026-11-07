<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    private function dir(): string
    {
        return storage_path('app/backups');
    }

    public function index(): View
    {
        File::ensureDirectoryExists($this->dir());

        return view('settings.backups', [
            'files' => collect(File::files($this->dir()))->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
                ->sortByDesc(fn ($f) => $f->getMTime())->values(),
            'external' => \App\Models\Setting::get('backup_path'),
        ]);
    }

    public function run(): RedirectResponse
    {
        $code = Artisan::call('app:backup');
        $output = trim(Artisan::output());

        return back()->with($code === 0 ? 'success' : 'error', $output ?: ($code === 0 ? 'Sauvegarde créée.' : 'La sauvegarde a échoué.'));
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = $this->dir().DIRECTORY_SEPARATOR.basename($file);
        abort_unless(is_file($path) && str_ends_with($path, '.zip'), 404);

        return response()->download($path);
    }

    public function destroy(string $file): RedirectResponse
    {
        $path = $this->dir().DIRECTORY_SEPARATOR.basename($file);
        abort_unless(is_file($path) && str_ends_with($path, '.zip'), 404);
        File::delete($path);

        return back()->with('success', 'Sauvegarde supprimée.');
    }
}
