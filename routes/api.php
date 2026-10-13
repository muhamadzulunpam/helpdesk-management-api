<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('categories', CategoryController::class)->except('show');

        Route::apiResource('tickets', TicketController::class);
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
        Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign']);

        Route::get('tickets/{ticket}/comments', [CommentController::class, 'index']);
        Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);
    });
});