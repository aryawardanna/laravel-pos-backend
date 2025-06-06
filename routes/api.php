<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

// product api
Route::apiResource('/products', App\Http\Controllers\Api\ProductController::class)->middleware('auth:sanctum');

// category api
Route::apiResource('/categories', App\Http\Controllers\Api\CategoryController::class)->middleware('auth:sanctum');
