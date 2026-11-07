<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect(AuthController::home(auth()->user()))
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthController::class, 'store'])->middleware('throttle:20,1');
});

Route::post('/deconnexion', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

require __DIR__.'/pos.php';
require __DIR__.'/admin.php';
