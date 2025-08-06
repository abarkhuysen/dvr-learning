<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\UserLessonProgress;
use Livewire\Volt\Component;

new class extends Component
{
    public $enrolledCourses;
    public $availableCourses;
    public $recentProgress;
    public $completionStats;

    public function mount()
    {
        $this->loadStudentData();
    }

    public function loadStudentData()
    {
        $user = auth()->user();

        // Get enrolled courses
        $this->enrolledCourses = $user->enrollments()
            ->with(['course.lessons'])
            ->where('status', 'active')
            ->get();

        // Get available courses for enrollment
        $enrolledCourseIds = $this->enrolledCourses->pluck('course_id');
        $this->availableCourses = Course::where('status', 'published')
            ->whereNotIn('id', $enrolledCourseIds)
            ->limit(3)
            ->get();

        // Get recent progress
        $this->recentProgress = UserLessonProgress::where('user_id', $user->id)
            ->with('lesson.course')
            ->latest()
            ->limit(5)
            ->get();

        // Calculate completion stats
        $this->completionStats = $this->calculateStats();
    }

    public function calculateStats()
    {
        $user = auth()->user();
        $totalLessons = 0;
        $completedLessons = 0;
        $totalWatchTime = 0;

        foreach ($this->enrolledCourses as $enrollment) {
            $courseLessons = $enrollment->course->lessons->count();
            $courseCompleted = UserLessonProgress::where('user_id', $user->id)
                ->whereHas('lesson', fn($q) => $q->where('course_id', $enrollment->course_id))
                ->where('completed', true)
                ->count();

            $totalLessons += $courseLessons;
            $completedLessons += $courseCompleted;
        }

        $totalWatchTime = UserLessonProgress::where('user_id', $user->id)
            ->sum('watch_time_seconds');

        return [
            'rate' => $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0,
            'hours' => round($totalWatchTime / 3600, 1)
        ];
    }

    public function viewCourse($courseId)
    {
        return redirect()->route('course.view', $courseId);
    }

    public function enrollInCourse($courseId)
    {
        $user = auth()->user();

        // Check if already enrolled
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->first();

        if ($existingEnrollment) {
            session()->flash('message', 'You are already enrolled in this course.');
            return;
        }

        // Create enrollment
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 0
        ]);

        session()->flash('message', 'Successfully enrolled in the course!');
        $this->loadStudentData();
    }
}; ?>

<x-layouts.student>
<div>
    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- Welcome Section -->
        <div class="mb-8">
            <flux:heading size="xl" class="mb-2">Welcome back, {{ auth()->user()->name }}!</flux:heading>
            <flux:text class="text-gray-600">Continue your learning journey</flux:text>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Enrolled Courses</flux:text>
                        <flux:heading size="xl">{{ $enrolledCourses->count() }}</flux:heading>
                    </div>
                    <flux:icon name="book-open" class="size-8 text-blue-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Completion Rate</flux:text>
                        <flux:heading size="xl">{{ number_format($completionStats['rate'], 1) }}%</flux:heading>
                    </div>
                    <flux:icon name="chart-bar" class="size-8 text-green-500" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Hours Watched</flux:text>
                        <flux:heading size="xl">{{ $completionStats['hours'] }}</flux:heading>
                    </div>
                    <flux:icon name="clock" class="size-8 text-purple-500" />
                </div>
            </flux:card>
        </div>

        <!-- My Courses Section -->
        @if($enrolledCourses->count() > 0)
        <flux:card class="p-6 mb-8">
            <flux:heading size="lg" class="mb-6">My Courses</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($enrolledCourses as $enrollment)
                    <div class="border rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="h-48 bg-gradient-to-br from-blue-400 to-blue-600 relative">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <flux:icon name="play-circle" class="size-16 text-white opacity-80" />
                            </div>
                        </div>

                        <div class="p-4">
                            <flux:heading size="md" class="mb-2">{{ $enrollment->course->title }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600 mb-4">
                                {{ Str::limit($enrollment->course->description, 100) }}
                            </flux:text>

                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-2">
                                    <flux:text size="sm">Progress</flux:text>
                                    <flux:text size="sm">{{ number_format($enrollment->progress_percentage) }}%</flux:text>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full"
                                         style="width: {{ $enrollment->progress_percentage }}%"></div>
                                </div>
                            </div>

                            <flux:button wire:click="viewCourse({{ $enrollment->course->id }})"
                                       variant="primary"
                                       class="w-full">
                                Continue Learning
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
        @endif

        <!-- Available Courses Section -->
        @if($availableCourses->count() > 0)
        <flux:card class="p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <flux:heading size="lg">Discover New Courses</flux:heading>
                <flux:button href="/courses" wire:navigate variant="outline">
                    View All Courses
                </flux:button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($availableCourses as $course)
                    <div class="border rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="h-48 bg-gradient-to-br from-green-400 to-green-600 relative">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <flux:icon name="plus-circle" class="size-16 text-white opacity-80" />
                            </div>
                        </div>

                        <div class="p-4">
                            <flux:heading size="md" class="mb-2">{{ $course->title }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600 mb-4">
                                {{ Str::limit($course->description, 100) }}
                            </flux:text>

                            <div class="flex justify-between items-center mb-4">
                                <flux:text size="sm" class="text-gray-500">
                                    {{ $course->lessons->count() }} lessons
                                </flux:text>
                                <flux:text size="sm" class="text-gray-500">
                                    Free
                                </flux:text>
                            </div>

                            <flux:button wire:click="enrollInCourse({{ $course->id }})"
                                       variant="primary"
                                       class="w-full">
                                Enroll Now
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
        @endif

        <!-- Recent Activity -->
        @if($recentProgress->count() > 0)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">Recent Activity</flux:heading>

            <div class="space-y-4">
                @foreach($recentProgress as $progress)
                    <div class="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg">
                        <flux:icon name="check-circle" class="size-6 text-green-500" />
                        <div class="flex-1">
                            <flux:text class="font-medium">{{ $progress->lesson->title }}</flux:text>
                            <flux:text size="sm" class="text-gray-600">
                                {{ $progress->lesson->course->title }} • {{ $progress->completed_at->diffForHumans() }}
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
        @endif
    </main>

    @if(session('message'))
        <flux:toast>{{ session('message') }}</flux:toast>
    @endif
</div>
</x-layouts.student>
