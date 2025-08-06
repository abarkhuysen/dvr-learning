<?php

use App\Models\Course;
use App\Models\Enrollment;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';
    public $filter = 'all';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilter()
    {
        $this->resetPage();
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
    }

    public function render()
    {
        $user = auth()->user();
        $enrolledCourseIds = $user->enrollments()->pluck('course_id');

        $courses = Course::where('status', 'published')
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->filter === 'available', function ($query) use ($enrolledCourseIds) {
                $query->whereNotIn('id', $enrolledCourseIds);
            })
            ->when($this->filter === 'enrolled', function ($query) use ($enrolledCourseIds) {
                $query->whereIn('id', $enrolledCourseIds);
            })
            ->withCount('lessons')
            ->latest()
            ->paginate(12);

        return view('livewire.student.courses', [
            'courses' => $courses,
            'enrolledCourseIds' => $enrolledCourseIds
        ]);
    }
}; ?>

<x-layouts.student>
<div class="min-h-screen bg-gray-50">
    <flux:header container class="border-b bg-white">
        <flux:navbar class="max-w-7xl mx-auto">
            <flux:navbar.brand href="/dashboard" wire:navigate>
                <flux:icon name="academic-cap" class="size-6" />
                LMS Platform
            </flux:navbar.brand>

            <flux:spacer />

            <flux:navbar.item href="/dashboard" wire:navigate>Dashboard</flux:navbar.item>
            <flux:navbar.item href="/courses" wire:navigate class="font-semibold">Browse Courses</flux:navbar.item>
            <flux:navbar.item icon="bell" badge="3" />
            <flux:dropdown>
                <flux:dropdown.trigger>
                    <flux:avatar size="sm" src="{{ auth()->user()->avatar }}" />
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
        <!-- Page Header -->
        <div class="mb-8">
            <flux:heading size="xl" class="mb-2">Course Catalog</flux:heading>
            <flux:text class="text-gray-600">Discover and enroll in courses to expand your knowledge</flux:text>
        </div>

        <!-- Search and Filters -->
        <flux:card class="p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search courses..."
                        icon="magnifying-glass"
                        class="w-full"
                    />
                </div>

                <div class="flex gap-2">
                    <flux:button
                        wire:click="$set('filter', 'all')"
                        variant="{{ $filter === 'all' ? 'primary' : 'outline' }}"
                    >
                        All Courses
                    </flux:button>
                    <flux:button
                        wire:click="$set('filter', 'available')"
                        variant="{{ $filter === 'available' ? 'primary' : 'outline' }}"
                    >
                        Available
                    </flux:button>
                    <flux:button
                        wire:click="$set('filter', 'enrolled')"
                        variant="{{ $filter === 'enrolled' ? 'primary' : 'outline' }}"
                    >
                        My Courses
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Course Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            @foreach($courses as $course)
                <flux:card class="overflow-hidden hover:shadow-lg transition-shadow">
                    <!-- Course Image -->
                    <div class="h-48 bg-gradient-to-br from-blue-400 to-blue-600 relative">
                        <div class="absolute inset-0 flex items-center justify-center">
                            @if(in_array($course->id, $enrolledCourseIds->toArray()))
                                <flux:icon name="check-circle" class="size-16 text-white opacity-80" />
                            @else
                                <flux:icon name="play-circle" class="size-16 text-white opacity-80" />
                            @endif
                        </div>

                        @if(in_array($course->id, $enrolledCourseIds->toArray()))
                            <div class="absolute top-4 right-4">
                                <flux:badge color="green">Enrolled</flux:badge>
                            </div>
                        @endif
                    </div>

                    <!-- Course Content -->
                    <div class="p-4">
                        <flux:heading size="md" class="mb-2">{{ $course->title }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600 mb-4 line-clamp-3">
                            {{ $course->description }}
                        </flux:text>

                        <!-- Course Meta -->
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center space-x-2">
                                <flux:icon name="book-open" class="size-4 text-gray-400" />
                                <flux:text size="sm" class="text-gray-500">
                                    {{ $course->lessons_count }} lessons
                                </flux:text>
                            </div>
                            <flux:text size="sm" class="text-gray-500">
                                Free
                            </flux:text>
                        </div>

                        <!-- Action Button -->
                        @if(in_array($course->id, $enrolledCourseIds->toArray()))
                            <flux:button
                                href="/course/{{ $course->id }}"
                                wire:navigate
                                variant="primary"
                                class="w-full"
                            >
                                Continue Learning
                            </flux:button>
                        @else
                            <flux:button
                                wire:click="enrollInCourse({{ $course->id }})"
                                variant="outline"
                                class="w-full"
                            >
                                Enroll Now
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <!-- Empty State -->
        @if($courses->count() === 0)
            <div class="text-center py-12">
                <flux:icon name="book-open" class="size-16 text-gray-400 mx-auto mb-4" />
                <flux:heading size="lg" class="mb-2">No courses found</flux:heading>
                <flux:text class="text-gray-600 mb-6">
                    @if($search)
                        No courses match your search criteria. Try adjusting your search terms.
                    @else
                        No courses are available at the moment. Check back later!
                    @endif
                </flux:text>
                @if($search)
                    <flux:button wire:click="$set('search', '')" variant="outline">
                        Clear Search
                    </flux:button>
                @endif
            </div>
        @endif

        <!-- Pagination -->
        <div class="flex justify-center">
            {{ $courses->links() }}
        </div>
    </main>

    @if(session('message'))
        <flux:toast>{{ session('message') }}</flux:toast>
    @endif
</div>
</x-layouts.student>
