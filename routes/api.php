<?php

use App\Http\Controllers\Api\V1\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Api\V1\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Api\V1\Admin\RoomFacilityController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\RoomController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function (): void {
    Route::prefix('v1/auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('register');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('login');

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::prefix('v1')->name('v1.')->middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');

        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
            Route::post('rooms', [AdminRoomController::class, 'store'])->name('rooms.store');
            Route::match(['put', 'patch'], 'rooms/{room}', [AdminRoomController::class, 'update'])->name('rooms.update');
            Route::delete('rooms/{room}', [AdminRoomController::class, 'destroy'])->name('rooms.destroy');
            Route::post('rooms/{room}/facilities/sync', [RoomFacilityController::class, 'sync'])->name('rooms.facilities.sync');

            Route::get('facilities', [AdminFacilityController::class, 'index'])->name('facilities.index');
            Route::post('facilities', [AdminFacilityController::class, 'store'])->name('facilities.store');
            Route::get('facilities/{facility}', [AdminFacilityController::class, 'show'])->name('facilities.show');
            Route::match(['put', 'patch'], 'facilities/{facility}', [AdminFacilityController::class, 'update'])->name('facilities.update');
            Route::delete('facilities/{facility}', [AdminFacilityController::class, 'destroy'])->name('facilities.destroy');

            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
            Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status.update');
        });
    });
});
