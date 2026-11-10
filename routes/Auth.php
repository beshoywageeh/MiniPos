<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

Route::group(['middleware' => ['guest']], function () {
    Route::view('/', 'Auth.register');
    Route::post('/register', RegisterController::class);
    Route::view('/login', 'Auth.login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::group(['middleware' => ['auth']], function () {
    Route::post('/logout', [LoginController::class, 'logout']);
});
