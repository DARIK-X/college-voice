<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ElectionController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\DeviceIdentity;
use Illuminate\Support\Facades\Route;

Route::middleware(DeviceIdentity::class)->group(function () {
    Route::get('/', [ElectionController::class, 'home'])->name('home');
    Route::post('/vote', [ElectionController::class, 'vote'])->middleware('throttle:vote');
});
Route::get('/result', [ElectionController::class, 'result'])->name('result');
Route::get('/results/data', [ElectionController::class, 'data'])->middleware('throttle:results');
Route::get('/admin/login', [AdminController::class, 'login'])->name('login');
Route::post('/admin/login', [AdminController::class, 'authenticate'])->middleware('throttle:login');
Route::middleware(AdminOnly::class)->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/panel', [AdminController::class, 'panel'])->name('panel');
    Route::post('/panel/state', [AdminController::class, 'state'])->middleware('throttle:admin');
    Route::get('/candidate', [AdminController::class, 'candidates'])->name('candidate');
    Route::post('/candidate', [AdminController::class, 'save'])->middleware('throttle:admin');
    Route::post('/candidate/{candidate}', [AdminController::class, 'save'])->middleware('throttle:admin');
    Route::post('/candidate/{candidate}/remove', [AdminController::class, 'remove'])->middleware('throttle:admin');
    Route::post('/candidate/{candidate}/adjust', [AdminController::class, 'adjust'])->middleware('throttle:admin');
});
