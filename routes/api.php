<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// post login
Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

// logout (butuh token)
Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])
    ->middleware('auth:sanctum');
Route::post('/logout-all', [\App\Http\Controllers\Api\AuthController::class, 'logoutAll'])
    ->middleware('auth:sanctum');

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

// Kategori Menu API
Route::prefix('categories')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\CategoryController::class, 'index'])
        ->middleware('auth:sanctum')
        ->name('api.category.index');

    Route::post('/', [App\Http\Controllers\Api\CategoryController::class, 'store'])
        ->middleware('auth:sanctum')
        ->name('api.category.store');

    Route::get('/{id}', [App\Http\Controllers\Api\CategoryController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('api.category.show');

    Route::put('/{id}', [App\Http\Controllers\Api\CategoryController::class, 'update'])
        ->middleware('auth:sanctum')
        ->name('api.category.update');

    Route::delete('/{id}', [App\Http\Controllers\Api\CategoryController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('api.category.destroy');
});
