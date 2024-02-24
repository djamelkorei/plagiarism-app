<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BalanceLineController;
use App\Http\Controllers\SessionController;
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

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
    Route::resource('/Assignment', AssignmentController::class)->only(['show', 'create', 'store', 'edit', 'update']);;
    Route::resource('/profile', BalanceController::class)->only(['show', 'create', 'store', 'edit', 'update']);;
    Route::resource('/profile', BalanceLineController::class)->only(['show', 'create', 'store', 'edit', 'update']);;
    Route::resource('posts', SessionController::class)->only(['show', 'create', 'store', 'edit', 'update']);;
});


require __DIR__.'/auth.php';
