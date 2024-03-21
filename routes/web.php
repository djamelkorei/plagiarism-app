<?php

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

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

Route::get('/assignments/{assignmentId}/webhook', function (Request $request, $assignmentId) {
    if($request->hasHeader('x-token')
        && config('scraper.api_key') == $request->header('x-token')
        && $assignmentId != null) {
        $assignment = Assignment::find($assignmentId);
        if(isset($assignment)) {
            return response()->json([ 'res' => 'success', 'id' => $assignment->id]);
        }
    }
    return response()->json([ 'res' => 'error']);
})->name('assignments.webhook');

Route::middleware('auth')->group(function () {
    Volt::route('/dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('/assignments', 'pages.assignments')->name('assignments');
    Route::view('/profile', 'profile')->name('profile');
    Route::get('/assignments/{assignmentId}/download', function ($assignmentId) {
        $assignment = Assignment::find($assignmentId);
        if (isset($assignment) && ($assignment->user_id == Auth::user()->id || Auth::user()->hasRole('super-admin'))) {
            return Storage::disk('s3')->response($assignment->download_link);
        }
        return null;
    })->name('assignments.download');

    Volt::route('/accounts', 'pages.accounts')
        ->middleware('role:super-admin')
        ->name('accounts');

    Volt::route('/users', 'pages.users')
        ->middleware('role:super-admin')
        ->name('users');
});


require __DIR__ . '/auth.php';
