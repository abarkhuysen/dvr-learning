<?php

use App\Models\Enrollment;
use App\Models\UserLessonProgress;
use Livewire\Volt\Component;

new class extends Component
{
    public $user;
    public $stats;
    public $recentActivity;
    public $achievements;

    public function mount()
    {
        $this->user = auth()->user();
        $this->loadProfileData();
    }

    public function loadProfileData()
    {
        // Calculate learning stats
        $enrollments = Enrollment::where('user_id', $this->user->id)->get();
        $totalProgress = UserLessonProgress::where('user_id', $this->user->id)->get();

        $this->stats = [
            'courses_enrolled' => $enrollments->where('status', 'active')->count(),
            'courses_completed' => $enrollments->where('status', 'completed')->count(),
            'lessons_completed' => $totalProgress->where('completed', true)->count(),
            'total_watch_time' => round($totalProgress->sum('watch_time_seconds') / 3600, 1),
            'avg_completion_rate' => $enrollments->count() > 0 ?
                round($enrollments->avg('progress_percentage'), 1) : 0
        ];

        // Get recent activity
        $this->recentActivity = UserLessonProgress::where('user_id', $this->user->id)
            ->with(['lesson.course'])
            ->where('completed', true)
            ->latest('completed_at')
            ->limit(10)
            ->get();

        // Calculate achievements
        $this->achievements = $this->calculateAchievements();
    }

    public function calculateAchievements()
    {
        $achievements = [];

        // First Course Achievement
        if ($this->stats['courses_enrolled'] >= 1) {
            $achievements[] = [
                'title' => 'First Steps',
                'description' => 'Enrolled in your first course',
                'icon' => 'academic-cap',
                'color' => 'blue',
                'earned' => true
            ];
        }

        // First Completion
        if ($this->stats['lessons_completed'] >= 1) {
            $achievements[] = [
                'title' => 'Getting Started',
                'description' => 'Completed your first lesson',
                'icon' => 'check-circle',
                'color' => 'green',
                'earned' => true
            ];
        }

        // Course Completion
        if ($this->stats['courses_completed'] >= 1) {
            $achievements[] = [
                'title' => 'Course Master',
                'description' => 'Completed your first course',
                'icon' => 'trophy',
                'color' => 'yellow',
                'earned' => true
            ];
        }

        // Dedication Badge
        if ($this->stats['lessons_completed'] >= 10) {
            $achievements[] = [
                'title' => 'Dedicated Learner',
                'description' => 'Completed 10 or more lessons',
                'icon' => 'fire',
                'color' => 'red',
                'earned' => true
            ];
        }

        // Time Investment
        if ($this->stats['total_watch_time'] >= 5) {
            $achievements[] = [
                'title' => 'Time Investor',
                'description' => 'Watched 5+ hours of content',
                'icon' => 'clock',
                'color' => 'purple',
                'earned' => true
            ];
        }

        // Upcoming achievements (not earned yet)
        if ($this->stats['courses_completed'] < 3) {
            $achievements[] = [
                'title' => 'Triple Threat',
                'description' => 'Complete 3 courses',
                'icon' => 'star',
                'color' => 'gray',
                'earned' => false
            ];
        }

        return $achievements;
    }

    public function render()
    {
        return view('livewire.student.profile');
    }
}; ?>

<x-layouts.student>
<div class="min-h-screen bg-gray-50">
    <flux:header container class="border-b bg-white">
        <flux:navbar class="max-w-7xl mx-auto">
            <flux:navbar.brand href="/dashboard" wire:navigate>
                <flux:icon name="academic-cap" class="size-6" />
                {{ config('app.name') }}
            </flux:navbar.brand>

            <flux:spacer />

            <flux:navbar.item href="/dashboard" wire:navigate>Dashboard</flux:navbar.item>
            <flux:navbar.item href="/courses" wire:navigate>Browse Courses</flux:navbar.item>
            <flux:navbar.item href="/profile" wire:navigate class="font-semibold">Profile</flux:navbar.item>
            <flux:navbar.item icon="bell" badge="3" />
            <flux:dropdown>
                <flux:dropdown.trigger>
                    <flux:avatar size="sm" src="{{ $user->avatar }}" />
                </flux:dropdown.trigger>
                <flux:dropdown.menu>
                    <flux:dropdown.item icon="user-circle" href="/profile">Profile</flux:dropdown.item>
                    <flux:dropdown.item icon="cog" href="/settings/profile">Settings</flux:dropdown.item>
                    <flux:dropdown.item separator />
                    <flux:dropdown.item icon="arrow-left-start-on-rectangle" href="/logout">Logout</flux:dropdown.item>
                </flux:dropdown.menu>
            </flux:dropdown>
        </flux:navbar>
    </flux:header>

    <main class="max-w-7xl mx-auto py-8 px-4">
        <!-- Profile Header -->
        <flux:card class="p-6 mb-8">
            <div class="flex items-center space-x-6">
                <flux:avatar size="lg" src="{{ $user->avatar }}" />
                <div class="flex-1">
                    <flux:heading size="xl" class="mb-2">{{ $user->name }}</flux:heading>
                    <flux:text class="text-gray-600 mb-2">{{ $user->email }}</flux:text>
                    @if($user->bio)
                        <flux:text class="text-gray-700">{{ $user->bio }}</flux:text>
                    @endif
                </div>
                <flux:button href="/settings/profile" wire:navigate variant="outline" icon="pencil">
                    Edit Profile
                </flux:button>
            </div>
        </flux:card>

        <!-- Learning Statistics -->
        <flux:card class="p-6 mb-8">
            <flux:heading size="lg" class="mb-6">Learning Statistics</flux:heading>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                <div class="text-center">
                    <div class="bg-blue-100 rounded-full p-3 w-16 h-16 flex items-center justify-center mx-auto mb-2">
                        <flux:icon name="book-open" class="size-8 text-blue-600" />
                    </div>
                    <flux:heading size="xl" class="mb-1">{{ $stats['courses_enrolled'] }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Active Courses</flux:text>
                </div>

                <div class="text-center">
                    <div class="bg-green-100 rounded-full p-3 w-16 h-16 flex items-center justify-center mx-auto mb-2">
                        <flux:icon name="check-circle" class="size-8 text-green-600" />
                    </div>
                    <flux:heading size="xl" class="mb-1">{{ $stats['courses_completed'] }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Completed</flux:text>
                </div>

                <div class="text-center">
                    <div class="bg-purple-100 rounded-full p-3 w-16 h-16 flex items-center justify-center mx-auto mb-2">
                        <flux:icon name="play" class="size-8 text-purple-600" />
                    </div>
                    <flux:heading size="xl" class="mb-1">{{ $stats['lessons_completed'] }}</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Lessons</flux:text>
                </div>

                <div class="text-center">
                    <div class="bg-orange-100 rounded-full p-3 w-16 h-16 flex items-center justify-center mx-auto mb-2">
                        <flux:icon name="clock" class="size-8 text-orange-600" />
                    </div>
                    <flux:heading size="xl" class="mb-1">{{ $stats['total_watch_time'] }}h</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Watch Time</flux:text>
                </div>

                <div class="text-center">
                    <div class="bg-indigo-100 rounded-full p-3 w-16 h-16 flex items-center justify-center mx-auto mb-2">
                        <flux:icon name="chart-bar" class="size-8 text-indigo-600" />
                    </div>
                    <flux:heading size="xl" class="mb-1">{{ $stats['avg_completion_rate'] }}%</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Avg Progress</flux:text>
                </div>
            </div>
        </flux:card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Achievements -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-6">Achievements</flux:heading>

                <div class="space-y-4">
                    @foreach($achievements as $achievement)
                        <div class="flex items-center space-x-4 p-3 rounded-lg
                                    {{ $achievement['earned'] ? 'bg-gray-50' : 'bg-gray-100 opacity-60' }}">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center
                                            {{ $achievement['earned'] ? 'bg-' . $achievement['color'] . '-100' : 'bg-gray-200' }}">
                                    <flux:icon name="{{ $achievement['icon'] }}"
                                             class="size-6 {{ $achievement['earned'] ? 'text-' . $achievement['color'] . '-600' : 'text-gray-400' }}" />
                                </div>
                            </div>
                            <div class="flex-1">
                                <flux:text class="font-medium {{ $achievement['earned'] ? '' : 'text-gray-500' }}">
                                    {{ $achievement['title'] }}
                                </flux:text>
                                <flux:text size="sm" class="text-gray-600">
                                    {{ $achievement['description'] }}
                                </flux:text>
                            </div>
                            @if($achievement['earned'])
                                <flux:badge color="{{ $achievement['color'] }}" size="sm">Earned</flux:badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>

            <!-- Recent Activity -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-6">Recent Activity</flux:heading>

                @if($recentActivity->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentActivity as $activity)
                            <div class="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg">
                                <flux:icon name="check-circle" class="size-6 text-green-500 flex-shrink-0" />
                                <div class="flex-1">
                                    <flux:text class="font-medium">{{ $activity->lesson->title }}</flux:text>
                                    <flux:text size="sm" class="text-gray-600">
                                        {{ $activity->lesson->course->title }} • {{ $activity->completed_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon name="clock" class="size-12 text-gray-400 mx-auto mb-3" />
                        <flux:text class="text-gray-600">No recent activity</flux:text>
                        <flux:text size="sm" class="text-gray-500">Start learning to see your progress here</flux:text>
                    </div>
                @endif
            </flux:card>
        </div>
    </main>
</div>
</x-layouts.student>
