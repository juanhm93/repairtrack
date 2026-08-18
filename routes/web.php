<?php

use App\Http\Controllers\Admin\OwnerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified' /* , 'admin' */])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [OwnerController::class, 'index'])->name('index');
        Route::post('migrate', [OwnerController::class, 'migrate'])->middleware('throttle:3,1')->name('migrate');
        Route::post('cache', [OwnerController::class, 'clearCache'])->middleware('throttle:6,1')->name('cache');
    });

require __DIR__.'/settings.php';
