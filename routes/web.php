<?php

use App\Http\Controllers\BlingAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Bling OAuth
Route::middleware('auth')->group(function () {
    Route::get('/bling/authorize/{account}', [BlingAuthController::class, 'authorize'])->name('bling.authorize');
    Route::get('/bling/callback', [BlingAuthController::class, 'callback'])->name('bling.callback');
    Route::get('/bling/status', [BlingAuthController::class, 'status'])->name('bling.status');
});
