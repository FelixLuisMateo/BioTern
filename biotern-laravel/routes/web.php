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
