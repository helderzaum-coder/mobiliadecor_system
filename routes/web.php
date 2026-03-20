<?php

use App\Http\Controllers\BlingAuthController;
use App\Http\Controllers\MercadoLivreAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Bling OAuth (sem middleware auth para permitir reautorizacao direta em producao)
Route::get('/bling/authorize/{account}', [BlingAuthController::class, 'authorize'])->name('bling.authorize');
Route::get('/bling/callback', [BlingAuthController::class, 'callback'])->name('bling.callback');
Route::get('/bling/status', [BlingAuthController::class, 'status'])->name('bling.status');

// Bling Webhooks → registrado em bootstrap/app.php (fora do middleware web)

// Mercado Livre OAuth (sem middleware auth para permitir callback do ML)
Route::get('/ml/authorize/{account}', [MercadoLivreAuthController::class, 'authorize'])->name('ml.authorize');
Route::get('/ml/callback', [MercadoLivreAuthController::class, 'callback'])->name('ml.callback');
Route::get('/ml/status', [MercadoLivreAuthController::class, 'status'])->name('ml.status');
