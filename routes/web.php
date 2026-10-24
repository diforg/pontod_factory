<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilamentController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PlateController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/filaments', [FilamentController::class, 'index'])->name('filaments.index');
    Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/plates', [PlateController::class, 'index'])->name('plates.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
