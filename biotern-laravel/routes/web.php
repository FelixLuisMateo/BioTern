<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('index');
});

// Students routes (simple closures returning the existing `students` view)
Route::get('/students', function () {
    return view('students');
})->name('students.index');

Route::get('/students/view', function () {
    return view('students');
});

Route::get('/students/create', function () {
    return view('students');
});

Route::get('students/attendance', function () {
    return view('attendance');
});

Route::get('students/demo-biometric', function () {
    return view('demo-biometric');
});

// Convenience routes matching navigation
Route::get('/demo-biometric', function () {
    return view('demo-biometric');
});

Route::get('/attendance', function () {
    return view('attendance');
});

Route::get('register_submit', function () {
    return view('register_submit');
})->name('register_submit');

// Accept form POSTs from the frontend registration form and render
// the `register_submit` view which contains the processing logic.
use App\Http\Controllers\RegisterSubmitController;

Route::post('register_submit', [RegisterSubmitController::class, 'handle'])->name('register_submit.post');

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    });
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
