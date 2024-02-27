<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Redirect;
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
    return Redirect::to('/login');
})->middleware('guest');


Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('assignments', 'assignments')->name('assignments');
    Route::view('profile', 'profile')->name('profile');
});


require __DIR__.'/auth.php';
