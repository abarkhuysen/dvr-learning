<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>{{ config('app.name') }}</title>
        @fluxAppearance
        @include('partials.head')
    </head>
    <body class="bg-gray-50 text-gray-900">
        <!-- Role Switcher Banner -->
        @livewire('role-switcher')

        <!-- Navigation -->
        <header class="bg-white border-b {{ auth()->check() && auth()->user()->isActingAsStudent() ? '' : 'border-t-4 border-t-blue-500' }}">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo and Navigation -->
                    <div class="flex items-center space-x-8">
                        <a href="/dashboard" wire:navigate class="flex items-center space-x-2">
                            <flux:icon name="academic-cap" class="size-8 text-blue-600" />
                            <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                        </a>

                        <nav class="hidden md:flex space-x-6">
                            <a href="/dashboard" wire:navigate class="text-gray-700 hover:text-blue-600 font-medium">
                                Dashboard
                            </a>
                        </nav>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        @if(auth()->check() && auth()->user()->isAdmin() && !auth()->user()->isActingAsStudent())
                            <flux:button href="/admin" variant="ghost" icon="arrow-left">
                                Back to Admin
                            </flux:button>
                        @endif

                        @if(auth()->check())
                            <flux:dropdown>
                                <flux:button variant="ghost" icon:trailing="chevron-down" class="flex items-center space-x-2">
                                    @if(auth()->user()->avatar)
                                        <img src="{{ auth()->user()->avatar }}" class="size-8 rounded-full" alt="Avatar">
                                    @else
                                        <div class="size-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-medium">
                                            {{ auth()->user()->initials() }}
                                        </div>
                                    @endif
                                    <span class="hidden md:block">{{ auth()->user()->name }}</span>
                                </flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="user-circle" href="/profile" wire:navigate>
                                        Profile
                                    </flux:menu.item>
                                    <flux:menu.item icon="cog" href="/settings/profile" wire:navigate>
                                        Settings
                                    </flux:menu.item>

                                    @if(auth()->user()->canSwitchRoles() && !auth()->user()->isActingAsStudent())
                                        <flux:menu.separator />
                                        <flux:menu.item icon="arrows-right-left" wire:click="$dispatch('switch-to-student')">
                                            View as Student
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.separator />
                                    <flux:menu.item icon="arrow-left-start-on-rectangle">
                                        <form method="POST" action="{{ route('logout') }}" class="inline">
                                            @csrf
                                            <button type="submit" class="w-full text-left">
                                                Sign out
                                            </button>
                                        </form>
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @else
                            <flux:button href="/login" wire:navigate variant="primary">
                                Sign In
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="min-h-[calc(100vh-4rem)]">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
