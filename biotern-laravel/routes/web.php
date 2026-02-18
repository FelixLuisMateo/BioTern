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

Route::get('/login', function () {
    return view('auth-login-cover');
})->name('login');

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

// Accept POST submissions from the demo-biometric form (view contains inline
// processing logic). This lets the same view handle POSTed data without a
// MethodNotAllowed exception during quick local testing.
Route::post('/demo-biometric', function () {
    return view('demo-biometric');
});

Route::get('/attendance', function () {
    return view('attendance');
});

Route::get('register_submit', function () {
    return view('register_submit');
})->name('register_submit');

// Also expose a simpler `/register` path for convenience (served by controller)
Route::get('/register', [App\Http\Controllers\RegisterSubmitController::class, 'show'])->name('register');

// Accept form POSTs from the frontend registration form and render
// the `register_submit` view which contains the processing logic.
Route::post('register_submit', [App\Http\Controllers\RegisterSubmitController::class, 'handle'])->name('register_submit.post');

// Backwards-compatible redirect: old URL used in some views
Route::get('/auth/register', function () {
    return redirect('/register');
});

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;

// Login routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login.show');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Local-only helper to create an admin user if none exists.
Route::get('/setup-admin', function () {
    if (!app()->environment('local')) {
        abort(403, 'Forbidden');
    }
    $email = 'admin@biotern.com';
    $exists = \Illuminate\Support\Facades\DB::table('users')->where('email', $email)->exists();
    if ($exists) {
        return 'Admin already exists';
    }
    $id = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
        'name' => 'Admin User',
        'email' => $email,
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return "Created admin id={$id} with password 'password' (local only).";
});

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
});

// The following route groups reference controllers that are not present
// in this repository copy. Disable them briefly so artisan commands like
// `route:list` can run. Re-enable (remove the surrounding `if (false)`)
// once the missing controllers are implemented.
if (false) {
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
}
