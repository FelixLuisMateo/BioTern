<?php
require_once dirname(__DIR__) . '/config/db.php';
use App\Http\Controllers\Common\BiometricController;

Route::post('/api/biometric/scan', [BiometricController::class, 'scan'])->name('biometric.scan');
Route::post('/api/biometric/register', [BiometricController::class, 'register'])->name('biometric.register');
Route::get('/api/biometric/verify/{student}', [BiometricController::class, 'verify'])->name('biometric.verify');

