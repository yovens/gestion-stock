<?php

/*
 * Routes caisse — à inclure dans routes/web.php :  require __DIR__.'/pos.php';
 *
 * Enregistrer les middlewares Spatie dans bootstrap/app.php :
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias([
 *           'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
 *           'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
 *       ]);
 *   })
 */

use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::middleware('permission:cash.open_close')->group(function () {
        Route::get('/caisse/ouvrir', [CashSessionController::class, 'create'])->name('cash.open');
        Route::post('/caisse/ouvrir', [CashSessionController::class, 'store'])->name('cash.store');
        Route::get('/caisse/fermer', [CashSessionController::class, 'edit'])->name('cash.close');
        Route::post('/caisse/fermer', [CashSessionController::class, 'update'])->name('cash.update');
    });

    Route::get('/caisse/{session}/rapport', [CashSessionController::class, 'report'])->name('cash.report');

    Route::middleware('permission:pos.access')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/produits', [PosController::class, 'products'])->name('products');
        Route::get('/clients', [PosController::class, 'customers'])->name('customers');
        Route::post('/ventes', [PosController::class, 'store'])->name('store');
        Route::get('/attente', [PosController::class, 'held'])->name('held');
        Route::post('/attente/{sale}/reprendre', [PosController::class, 'resume'])->name('resume');
        Route::delete('/attente/{sale}', [PosController::class, 'destroyHeld'])->name('held.destroy');
        Route::get('/ventes/{sale}/recu', [PosController::class, 'receipt'])->name('receipt');
    });
});
