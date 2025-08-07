<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\UserLessonProgress;
use Livewire\Volt\Component;

new class extends Component {
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
        return $this->redirect(route('course.view', $courseId), navigate: true);
    }

    public function continueLearning($courseId)
    {
        // Redirect to course viewer which will automatically select the next incomplete lesson
        return $this->redirect(route('course.view', $courseId), navigate: true);
    }

}; ?>
<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <!-- Welcome Section -->
    <div class="">
        <flux:heading size="xl" class="mb-2">Welcome back, {{ auth()->user()->name }}!</flux:heading>
        <flux:text size="sm">Continue your learning journey</flux:text>
    </div>
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="lg" class="mb-2">Enrolled Courses</flux:text>
                    <flux:heading size="xl">{{ $enrolledCourses->count() }}</flux:heading>
                </div>
                <flux:icon name="book-open" class="size-8 text-blue-500"/>
            </div>
        </flux:card>
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="lg" class="mb-2">Completion Rate</flux:text>
                    <flux:heading size="xl">{{ number_format($completionStats['rate'], 1) }}%</flux:heading>
                </div>
                <flux:icon name="chart-bar" class="size-8 text-green-500"/>
            </div>
        </flux:card>
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="lg" class="mb-2">Hours Watched</flux:text>
                    <flux:heading size="xl">{{ $completionStats['hours'] }}</flux:heading>
                </div>
                <flux:icon name="clock" class="size-8 text-purple-500"/>
            </div>
        </flux:card>
    </div>

    <!-- My Courses Section -->
    @if($enrolledCourses->count() > 0)
        <flux:heading size="lg">My Courses</flux:heading>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($enrolledCourses as $enrollment)
                <flux:card class="hover:shadow-lg transition-shadow p-0">
                    <div class="h-48 bg-gradient-to-br from-blue-600 to-blue-900 relative rounded-t-xl">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <flux:icon name="play-circle" class="size-16 text-white opacity-80"/>
                        </div>
                    </div>
                    <div class="p-4">
                        <flux:heading size="md" class="mb-2">{{ $enrollment->course->title }}</flux:heading>
                        <flux:text size="sm" class="mb-4">
                            {{ Str::limit($enrollment->course->description, 100) }}
                        </flux:text>
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <flux:text size="sm">Progress</flux:text>
                                <flux:text size="sm">{{ number_format($enrollment->progress_percentage) }}%</flux:text>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-blue-800 h-2 rounded-full"
                                     style="width: {{ $enrollment->progress_percentage }}%"></div>
                            </div>
                        </div>
                        <flux:button wire:click="continueLearning({{ $enrollment->course->id }})"
                                     variant="primary"
                                     class="w-full">
                            @if($enrollment->progress_percentage == 0)
                                Start Course
                            @elseif($enrollment->progress_percentage == 100)
                                Review Course
                            @else
                                Continue Learning
                            @endif
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <!-- Recent Activity -->
    <flux:heading size="lg">Recent Activity</flux:heading>
    @if($recentProgress->count() > 0)

        <div class="space-y-4">
            @foreach($recentProgress as $progress)
                <flux:card class="flex items-center space-x-4 p-3">
                    <flux:icon name="check-circle" class="size-6 text-green-500"/>
                    <div class="flex-1">
                        <flux:heading size="lg">{{ $progress->lesson->title }}</flux:heading>
                        <flux:text size="sm">
                            {{ $progress->lesson->course->title }}
                            • {{ $progress->completed_at ? $progress->completed_at->diffForHumans() : 'busy'  }}
                        </flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>

    @endif
    @if(session('message'))
        <flux:toast>{{ session('message') }}</flux:toast>
    @endif
</div>
