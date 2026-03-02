<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Root route updated to load the blank starter canvas
Route::get('/', function () {
    return view('starter');
});

Route::get('/starter', function () {
    return view('starter');
});

// Authentication Pages
Route::get('/login', function () {
    return view('login');
});

Route::get('/register', function () {
    return view('register');
});

// Profile and Account Management
Route::get('/profile', function () {
    return view('profile');
});

Route::get('/user-profile', function () {
    return view('user-profile');
});

Route::get('/account-settings', function () {
    return view('account-settings');
});

Route::get('/change-password', function () {
    return view('change-password');
});

Route::get('/add-user', function () {
    return view('add-user');
});

// Error Pages
Route::get('/404-error-page', function () {
    return view('404-error-page');
});

