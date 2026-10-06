<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// post login
Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

// Bahan baku API
Route::prefix('bahan-baku')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\BahanBakuController::class, 'index'])
        ->middleware('auth:sanctum')
        ->name('api.bahan_baku.index');

    Route::post('/', [App\Http\Controllers\Api\BahanBakuController::class, 'store'])
        ->middleware('auth:sanctum')
        ->name('api.bahan_baku.store');

    Route::get('/{id}', [App\Http\Controllers\Api\BahanBakuController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('api.bahan_baku.show');

    Route::put('/{id}', [App\Http\Controllers\Api\BahanBakuController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('api.bahan_baku.update');

    Route::delete('/{id}', [App\Http\Controllers\Api\BahanBakuController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('api.bahan_baku.destroy');
});
