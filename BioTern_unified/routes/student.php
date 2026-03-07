<?php

use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\DTRController;
use App\Http\Controllers\Student\BiometricRegistrationController;
use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\HourTrackingController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('student.dashboard');

Route::get('/ojt-details', [DashboardController::class, 'ojtDetails'])->name('student.ojt-details');

Route::resource('dtr', DTRController::class, ['as' => 'student']);
Route::post('/dtr/{dtr}/upload', [DTRController::class, 'upload'])->name('student.dtr.upload');

Route::resource('biometric', BiometricRegistrationController::class, ['as' => 'student']);
Route::post('/biometric/register', [BiometricRegistrationController::class, 'register'])
    ->name('student.biometric.register');

Route::get('/hours/tracking', [HourTrackingController::class, 'tracking'])->name('student.hours.tracking');
Route::get('/hours/summary', [HourTrackingController::class, 'summary'])->name('student.hours.summary');

Route::get('/completion/status', [DashboardController::class, 'completionStatus'])
    ->name('student.completion.status');
Route::resource('certificates', CertificateController::class, ['as' => 'student']);