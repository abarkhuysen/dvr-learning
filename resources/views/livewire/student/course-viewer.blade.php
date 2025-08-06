<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\UserLessonProgress;
use Livewire\Volt\Component;

new class extends Component
{
    public Course $course;
    public $currentLesson;
    public $lessons;
    public $userProgress;
    public $enrollment;
    public $showCompletionModal = false;
    public $canAccessCourse = false;
    
    public function mount(Course $course)
    {
        $this->course = $course;
        $this->lessons = $course->lessons()->orderBy('order')->get();
        $this->loadEnrollmentAndProgress();
        $this->loadCurrentLesson();
    }
    
    public function loadEnrollmentAndProgress()
    {
        $user = auth()->user();
        
        // Check if user is enrolled
        $this->enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $this->course->id)
            ->first();
            
        $this->canAccessCourse = $this->enrollment && $this->enrollment->status === 'active';
        
        if ($this->canAccessCourse) {
            $this->userProgress = UserLessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $this->lessons->pluck('id'))
                ->get()
                ->keyBy('lesson_id');
        } else {
            $this->userProgress = collect();
        }
    }
    
    public function loadCurrentLesson()
    {
        if ($this->canAccessCourse) {
            // Find first incomplete lesson or first lesson
            $incompleteLesson = $this->lessons->first(function ($lesson) {
                return !$this->userProgress->get($lesson->id)?->completed;
            });
            
            $this->currentLesson = $incompleteLesson ?? $this->lessons->first();
        } else {
            // Show first free lesson or first lesson for preview
            $this->currentLesson = $this->lessons->where('is_free', true)->first() ?? $this->lessons->first();
        }
    }
    
    public function selectLesson($lessonId)
    {
        $lesson = $this->lessons->find($lessonId);
        
        if (!$lesson) return;
        
        // Check access permissions
        if (!$this->canAccessCourse && !$lesson->is_free) {
            session()->flash('error', 'Please enroll in this course to access this lesson.');
            return;
        }
        
        $this->currentLesson = $lesson;
    }
    
    public function markComplete()
    {
        if (!$this->canAccessCourse) {
            session()->flash('error', 'Please enroll in this course to track progress.');
            return;
        }
        
        UserLessonProgress::updateOrCreate(
            ['user_id' => auth()->id(), 'lesson_id' => $this->currentLesson->id],
            [
                'completed' => true, 
                'completed_at' => now(),
                'watch_time_seconds' => 0 // You can track actual watch time with JavaScript
            ]
        );
        
        $this->loadEnrollmentAndProgress();
        $this->showCompletionModal = true;
        
        // Update course progress
        $this->updateCourseProgress();
        
        // Auto-advance to next lesson
        $this->advanceToNextLesson();
    }
    
    public function updateCourseProgress()
    {
        if (!$this->enrollment) return;
        
        $totalLessons = $this->lessons->count();
        $completedLessons = $this->userProgress->where('completed', true)->count();
        
        $progressPercentage = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
        
        $this->enrollment->update([
            'progress_percentage' => $progressPercentage,
            'status' => $progressPercentage >= 100 ? 'completed' : 'active'
        ]);
    }
    
    public function advanceToNextLesson()
    {
        $currentIndex = $this->lessons->search(fn($l) => $l->id === $this->currentLesson->id);
        $nextIndex = $currentIndex + 1;
        
        if ($nextIndex < $this->lessons->count()) {
            $nextLesson = $this->lessons[$nextIndex];
            if ($this->canAccessCourse || $nextLesson->is_free) {
                $this->currentLesson = $nextLesson;
            }
        }
    }
    
    public function enrollInCourse()
    {
        $user = auth()->user();
        
        // Check if already enrolled
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $this->course->id)
            ->first();
            
        if ($existingEnrollment) {
            session()->flash('message', 'You are already enrolled in this course.');
            $this->loadEnrollmentAndProgress();
            return;
        }
        
        // Create enrollment
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $this->course->id,
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 0
        ]);
        
        session()->flash('message', 'Successfully enrolled in the course!');
        $this->loadEnrollmentAndProgress();
        $this->loadCurrentLesson();
    }
    
    public function render()
    {
        return view('livewire.student.course-viewer');
    }
}; ?>

<x-layouts.student>
<div class="min-h-screen bg-gray-50">
    <!-- Course Header -->
    <flux:header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between py-4">
                <div class="flex items-center space-x-4">
                    <flux:button href="/dashboard" wire:navigate variant="ghost" icon="arrow-left">
                        Back to Dashboard
                    </flux:button>
                    <flux:heading size="lg">{{ $course->title }}</flux:heading>
                </div>
                
                @if($canAccessCourse)
                    <div class="flex items-center space-x-4">
                        <flux:text size="sm" class="text-gray-600">
                            {{ $userProgress->where('completed', true)->count() }} / {{ $lessons->count() }} completed
                        </flux:text>
                        <div class="w-32 bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" 
                                 style="width: {{ $lessons->count() > 0 ? ($userProgress->where('completed', true)->count() / $lessons->count()) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </flux:header>

    <div class="flex">
        <!-- Sidebar - Lesson List -->
        <div class="w-80 bg-white border-r h-screen overflow-y-auto">
            <div class="p-4">
                <div class="flex justify-between items-center mb-4">
                    <flux:heading size="md">Course Content</flux:heading>
                    @if(!$canAccessCourse)
                        <flux:button wire:click="enrollInCourse" variant="primary" size="sm">
                            Enroll
                        </flux:button>
                    @endif
                </div>
                
                <div class="space-y-2">
                    @foreach($lessons as $lesson)
                        @php
                            $isCompleted = $userProgress->get($lesson->id)?->completed ?? false;
                            $canAccess = $canAccessCourse || $lesson->is_free;
                            $isSelected = $currentLesson?->id === $lesson->id;
                        @endphp
                        
                        <div class="border rounded-lg p-3 transition-colors
                                    {{ $isSelected ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50' }}
                                    {{ !$canAccess ? 'opacity-50' : 'cursor-pointer' }}"
                             @if($canAccess) wire:click="selectLesson({{ $lesson->id }})" @endif>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    @if($isCompleted)
                                        <flux:icon name="check-circle" class="size-5 text-green-500" />
                                    @elseif(!$canAccess)
                                        <flux:icon name="lock-closed" class="size-5 text-gray-400" />
                                    @else
                                        <flux:icon name="play-circle" class="size-5 text-gray-400" />
                                    @endif
                                    
                                    <div>
                                        <flux:text class="font-medium">{{ $lesson->title }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500">Lesson {{ $lesson->order }}</flux:text>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-1">
                                    @if($lesson->is_free)
                                        <flux:badge color="green" size="sm">Free</flux:badge>
                                    @endif
                                    @if(!$canAccess && !$lesson->is_free)
                                        <flux:badge color="gray" size="sm">Premium</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Main Content - Video Player -->
        <div class="flex-1">
            @if($currentLesson)
                <div class="p-6">
                    <!-- Video Player -->
                    <div class="bg-black rounded-lg mb-6" style="aspect-ratio: 16/9;">
                        @if($currentLesson->vimeo_video_id)
                            <iframe src="https://player.vimeo.com/video/{{ $currentLesson->vimeo_video_id }}?badge=0&autopause=0&player_id=0&app_id=58479"
                                    class="w-full h-full rounded-lg"
                                    frameborder="0"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    title="{{ $currentLesson->title }}">
                            </iframe>
                        @else
                            <div class="flex items-center justify-center h-full text-white">
                                <div class="text-center">
                                    <flux:icon name="play" class="size-16 mb-4 mx-auto opacity-50" />
                                    <flux:text>Video not available</flux:text>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Access Restriction Notice -->
                    @if(!$canAccessCourse && !$currentLesson->is_free)
                        <flux:card class="p-4 mb-6 bg-yellow-50 border-yellow-200">
                            <div class="flex items-center space-x-3">
                                <flux:icon name="lock-closed" class="size-6 text-yellow-600" />
                                <div>
                                    <flux:text class="font-medium text-yellow-800">Premium Content</flux:text>
                                    <flux:text size="sm" class="text-yellow-700">
                                        Enroll in this course to access all lessons and track your progress.
                                    </flux:text>
                                </div>
                                <flux:button wire:click="enrollInCourse" variant="primary" size="sm">
                                    Enroll Now
                                </flux:button>
                            </div>
                        </flux:card>
                    @endif

                    <!-- Lesson Info -->
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <flux:heading size="xl" class="mb-2">{{ $currentLesson->title }}</flux:heading>
                            @if($currentLesson->description)
                                <flux:text class="text-gray-600">{{ $currentLesson->description }}</flux:text>
                            @endif
                        </div>
                        
                        @if($canAccessCourse)
                            @if(!$userProgress->get($currentLesson->id)?->completed)
                                <flux:button wire:click="markComplete" variant="primary" icon="check">
                                    Mark Complete
                                </flux:button>
                            @else
                                <flux:badge color="green" icon="check">Completed</flux:badge>
                            @endif
                        @endif
                    </div>

                    <!-- Navigation -->
                    <div class="flex justify-between">
                        @php
                            $currentIndex = $lessons->search(fn($l) => $l->id === $currentLesson->id);
                            $prevLesson = $currentIndex > 0 ? $lessons[$currentIndex - 1] : null;
                            $nextLesson = $currentIndex < $lessons->count() - 1 ? $lessons[$currentIndex + 1] : null;
                            
                            $canAccessPrev = $prevLesson && ($canAccessCourse || $prevLesson->is_free);
                            $canAccessNext = $nextLesson && ($canAccessCourse || $nextLesson->is_free);
                        @endphp
                        
                        @if($prevLesson && $canAccessPrev)
                            <flux:button wire:click="selectLesson({{ $prevLesson->id }})" 
                                       variant="outline" 
                                       icon="arrow-left">
                                Previous: {{ Str::limit($prevLesson->title, 20) }}
                            </flux:button>
                        @else
                            <div></div>
                        @endif
                        
                        @if($nextLesson && $canAccessNext)
                            <flux:button wire:click="selectLesson({{ $nextLesson->id }})" 
                                       variant="primary" 
                                       icon:trailing="arrow-right">
                                Next: {{ Str::limit($nextLesson->title, 20) }}
                            </flux:button>
                        @elseif($nextLesson && !$canAccessNext)
                            <flux:button wire:click="enrollInCourse" 
                                       variant="primary" 
                                       icon:trailing="lock-closed">
                                Enroll to Continue
                            </flux:button>
                        @endif
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-full">
                    <flux:text class="text-gray-500">Select a lesson to start learning</flux:text>
                </div>
            @endif
        </div>
    </div>

    <!-- Completion Modal -->
    <flux:modal name="completion" :show="$showCompletionModal" wire:model="showCompletionModal">
        <div class="text-center p-6">
            <flux:icon name="check-circle" class="size-16 text-green-500 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-2">Lesson Complete!</flux:heading>
            <flux:text class="text-gray-600 mb-6">
                Great job! You've completed "{{ $currentLesson?->title }}".
            </flux:text>
            <flux:button wire:click="$set('showCompletionModal', false)" variant="primary">
                Continue Learning
            </flux:button>
        </div>
    </flux:modal>

    @if(session('message'))
        <flux:toast variant="success">{{ session('message') }}</flux:toast>
    @endif
    
    @if(session('error'))
        <flux:toast variant="danger">{{ session('error') }}</flux:toast>
    @endif
</div>
</x-layouts.student>