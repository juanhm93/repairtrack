<?php

use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('t/{token}', [PublicTicketController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{16,64}')
    ->name('public.tickets.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}/edit', [TicketController::class, 'edit'])->name('tickets.edit');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status.update');
});

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [OwnerController::class, 'index'])->name('index');
        Route::post('migrate', [OwnerController::class, 'migrate'])->middleware('throttle:3,1')->name('migrate');
        Route::post('cache', [OwnerController::class, 'clearCache'])->middleware('throttle:6,1')->name('cache');
    });

require __DIR__.'/settings.php';
