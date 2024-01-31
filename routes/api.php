<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::controller(UserController::class)->group(function () {
    Route::post('/login', 'login');
    Route::post('/register', 'register');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::controller(UserController::class)->group(function () {
        Route::get('/user', 'me');
        Route::post('/update-profile', 'updateProfile');
        Route::put('/change-password', 'changePassword');
        Route::delete('/logout', 'logout');
        Route::delete('/disable-user', 'disableUser');
        Route::delete('/delete-user', 'deleteUser');
    });

    Route::controller(EventController::class)->group(function () {
        Route::get('/events', 'getEvents');
        Route::get('/events/{slug}', 'getEvent');
        Route::post('/events', 'createEvent');
        Route::post('/events-update/{slug}', 'updateEvent');
        Route::delete('/events/{slug}', 'deleteEvent');
    });
});
