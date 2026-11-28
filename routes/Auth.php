<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['guest']], function () {
    Route::view('/register', 'Auth.register');
    Route::post('/register', RegisterController::class);
    Route::view('/', 'Auth.login')->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::group(['middleware' => ['auth']], function () {
    Route::post('/logout', [LoginController::class, 'logout']);
});
