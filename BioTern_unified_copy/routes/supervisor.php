<?php

use App\Http\Controllers\Supervisor\DashboardController;
use App\Http\Controllers\Supervisor\DTRController;
use App\Http\Controllers\Supervisor\StudentMonitoringController;
use App\Http\Controllers\Supervisor\EvaluationController;
use App\Http\Controllers\Supervisor\MessagingController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('supervisor.dashboard');

Route::resource('dtr', DTRController::class, ['as' => 'supervisor']);
Route::post('/dtr/{dtr}/approve', [DTRController::class, 'approve'])->name('supervisor.dtr.approve');
Route::post('/dtr/{dtr}/reject', [DTRController::class, 'reject'])->name('supervisor.dtr.reject');

Route::resource('students', StudentMonitoringController::class, ['as' => 'supervisor']);

Route::resource('evaluations', EvaluationController::class, ['as' => 'supervisor']);

Route::get('/messages', [MessagingController::class, 'inbox'])->name('supervisor.messages.inbox');
Route::get('/messages/send', [MessagingController::class, 'create'])->name('supervisor.messages.create');
Route::post('/messages', [MessagingController::class, 'store'])->name('supervisor.messages.store');

Route::get('/reports/attendance-concerns', [DashboardController::class, 'attendanceConcerns'])
    ->name('supervisor.reports.attendance-concerns');