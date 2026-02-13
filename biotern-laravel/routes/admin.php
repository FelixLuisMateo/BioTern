<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\SystemSettingsController;

Route::get('/dashboard', function () {
    return view('dashboard_admin');
})->name('admin.dashboard');

Route::resource('courses', CourseController::class, ['as' => 'admin']);
Route::resource('departments', DepartmentController::class, ['as' => 'admin']);
Route::resource('settings', SystemSettingsController::class, ['as' => 'admin']);

Route::get('/reports/internship-summary', [DashboardController::class, 'internshipSummary'])
    ->name('admin.reports.internship-summary');
Route::get('/reports/system-logs', [DashboardController::class, 'systemLogs'])
    ->name('admin.reports.system-logs');
