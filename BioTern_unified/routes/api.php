<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\InternshipController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('api')->group(function () {
    
    Route::apiResource('attendances', AttendanceController::class);
    Route::apiResource('students', StudentController::class);
    Route::apiResource('internships', InternshipController::class);
    
    Route::post('attendances/{attendance}/approve', [AttendanceController::class, 'approve']);
    Route::post('attendances/{attendance}/reject', [AttendanceController::class, 'reject']);
    Route::post('attendances/bulk-approve', [AttendanceController::class, 'bulkApprove']);
    Route::get('attendances/export', [AttendanceController::class, 'export']);
    
});