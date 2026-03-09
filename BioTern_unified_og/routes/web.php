<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.post');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    
    // Admin Routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        require 'admin.php';
    });
    
    // Coordinator Routes
    Route::middleware('coordinator')->prefix('coordinator')->group(function () {
        require 'coordinator.php';
    });
    
    // Supervisor Routes
    Route::middleware('supervisor')->prefix('supervisor')->group(function () {
        require 'supervisor.php';
    });
    
    // Student Routes
    Route::middleware('student')->prefix('student')->group(function () {
        require 'student.php';
    });
    
    // Biometric Routes
    require 'biometric.php';
});