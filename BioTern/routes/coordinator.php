<?php

use App\Http\Controllers\Coordinator\DashboardController;
use App\Http\Controllers\Coordinator\InternshipController;
use App\Http\Controllers\Coordinator\StudentInternshipController;
use App\Http\Controllers\Coordinator\DocumentGenerationController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('coordinator.dashboard');

Route::resource('internships', InternshipController::class, ['as' => 'coordinator']);
Route::resource('students', StudentInternshipController::class, ['as' => 'coordinator']);
Route::resource('documents', DocumentGenerationController::class, ['as' => 'coordinator']);

Route::post('/documents/{document}/generate', [DocumentGenerationController::class, 'generate'])
    ->name('coordinator.documents.generate');
Route::get('/documents/{document}/download', [DocumentGenerationController::class, 'download'])
    ->name('coordinator.documents.download');

Route::get('/reports/internship-progress', [DashboardController::class, 'internshipProgress'])
    ->name('coordinator.reports.internship-progress');
Route::get('/reports/student-hours', [DashboardController::class, 'studentHours'])
    ->name('coordinator.reports.student-hours');