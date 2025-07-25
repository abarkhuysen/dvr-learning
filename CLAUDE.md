# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel application using Livewire and Flux UI components. It's based on the Laravel Livewire starter kit and includes authentication, user settings, and a dashboard.

### Technology Stack
- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Livewire (full-stack reactive components)
- **UI Framework**: Livewire Flux 2.1
- **Styling**: Tailwind CSS 4.0
- **Build Tool**: Vite 7.0
- **Testing**: Pest PHP 3.8
- **Database**: SQLite (configured in phpunit.xml for testing)

## Development Commands

### Running the Application
```bash
# Start all development services (server, queue, logs, vite)
composer dev

# Individual services
php artisan serve        # Laravel server
php artisan queue:listen # Queue worker  
php artisan pail         # Log viewer
npm run dev             # Vite dev server
```

### Testing
```bash
# Run all tests
composer test
# OR
php artisan test

# Run specific test files
php artisan test --filter=AuthenticationTest
```

### Code Quality
```bash
# Format code (Laravel Pint)
./vendor/bin/pint

# Build assets
npm run build
```

### Database
```bash
# Run migrations
php artisan migrate

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

## Architecture Overview

### Livewire Volt Components
The application uses Livewire Volt for single-file components that combine PHP logic and Blade templates. Key patterns:

- **Location**: `resources/views/livewire/`
- **Structure**: PHP class at top, Blade template below
- **Example**: `resources/views/livewire/settings/profile.blade.php` contains both the component logic and view

### Authentication System
- Uses Laravel's built-in authentication with Livewire components
- Auth routes defined in `routes/auth.php`
- Auth components in `resources/views/livewire/auth/`
- Middleware protection on dashboard and settings routes

### UI Components
- **Flux Components**: Used throughout for consistent UI (`<flux:input>`, `<flux:button>`, etc.)
- **Custom Components**: Located in `resources/views/components/`
- **Layouts**: Main app layout in `resources/views/components/layouts/app.blade.php`

### Settings System
- Modular settings pages (profile, password, appearance)
- Each setting page is a separate Volt component
- Settings layout component provides consistent structure
- Routes use `Volt::route()` for direct component routing

### Asset Pipeline
- Vite handles CSS/JS compilation
- Entry points: `resources/css/app.css` and `resources/js/app.js`
- Tailwind CSS configured with Vite plugin
- Hot reloading enabled in development

### Testing Structure
- **Feature Tests**: End-to-end functionality testing
- **Unit Tests**: Individual component testing
- **Database**: Uses in-memory SQLite for tests
- **Test Organization**: Auth tests, settings tests, and general feature tests