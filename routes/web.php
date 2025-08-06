<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin() 
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
    Volt::route('course/{course}', 'student.course-viewer')->name('course.view');
    
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
});

// Vimeo webhook route (no authentication required)
Route::post('/webhooks/vimeo', [App\Http\Controllers\VimeoWebhookController::class, 'handle'])
    ->name('vimeo.webhook');

require __DIR__.'/auth.php';
