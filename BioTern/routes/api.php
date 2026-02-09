Route::middleware('api')->group(function () {
    // Biometric attendance recording endpoint
    Route::post('/attendance/biometric', [AttendanceController::class, 'store']);
});-+