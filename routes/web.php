<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        // Check if admin is acting as student
        if ($user->isActingAsStudent()) {
            return redirect('/dashboard');
        }

        return $user->isAdmin()
            ? redirect('/admin')
            : redirect('/dashboard');
    }

    return view('welcome');
})->name('home');

// Student routes (Livewire components)
Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Course routes
    Volt::route('courses', 'student.courses')->name('courses');
    Route::get('course/{course}', App\Livewire\Student\CourseViewer::class)->name('course.view');

    // Profile route
    Volt::route('profile', 'student.profile')->name('profile');

    // Settings routes
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// Admin routes (FilamentPHP handles these automatically)
Route::middleware(['auth', 'role:admin'])->group(function () {
    // FilamentPHP admin panel routes are auto-registered

    // Role switching route
    Route::get('/switch-to-student', function () {
        session(['acting_as' => 'student']);

        return redirect('/dashboard');
    })->name('switch-to-student');
});

// Vimeo webhook route (no authentication required)
Route::post('/webhooks/vimeo', [App\Http\Controllers\VimeoWebhookController::class, 'handle'])
    ->name('vimeo.webhook');

require __DIR__.'/auth.php';
