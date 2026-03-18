<?php

use App\Http\Controllers\BlingAuthController;
use App\Http\Controllers\BlingWebhookController;
use App\Http\Controllers\MercadoLivreAuthController;
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

// Bling Webhooks (sem auth, sem CSRF — chamada externa do Bling)
Route::post('/webhook/bling/{account}', [BlingWebhookController::class, 'handle'])
    ->name('webhook.bling')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Mercado Livre OAuth
Route::middleware('auth')->group(function () {
    Route::get('/ml/authorize/{account}', [MercadoLivreAuthController::class, 'authorize'])->name('ml.authorize');
    Route::get('/ml/callback', [MercadoLivreAuthController::class, 'callback'])->name('ml.callback');
    Route::get('/ml/status', [MercadoLivreAuthController::class, 'status'])->name('ml.status');
});
