<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $r) => \App\Http\Controllers\AuthController::home($r->user()));
    })
    ->withSchedule(function (Schedule $schedule) {
        // Sauvegarde quotidienne (l'ordinateur doit être allumé : voir INSTALLATION.md)
        $schedule->command('app:backup')->dailyAt('13:00')->withoutOverlapping();
        $schedule->command('app:backup')->dailyAt('19:30')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Accès refusé : message clair au lieu d'une page d'erreur brute
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => "Vous n'avez pas accès à cette fonction."], 403);
            }

            return response()->view('errors.403', ['message' => "Vous n'avez pas accès à cette page."], 403);
        });
    })->create();
