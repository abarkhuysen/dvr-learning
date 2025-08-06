<?php

namespace App\Livewire\Student;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\UserLessonProgress;
use Livewire\Attributes\Layout;
use Livewire\Component;

class CourseViewer extends Component
{
    public Course $course;

    public $currentLesson;

    public $lessons;

    public $userProgress;

    public $enrollment;

    public $showCompletionModal = false;

    public $courseCompleted = false;

    public function mount(Course $course)
    {
        // Check if user is enrolled
        $this->enrollment = Enrollment::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->course = $course;
        $this->lessons = $course->lessons()->orderBy('order')->get();
        $this->loadProgress();
        $this->loadCurrentLesson();
    }

    public function loadProgress()
    {
        $this->userProgress = UserLessonProgress::where('user_id', auth()->id())
            ->whereIn('lesson_id', $this->lessons->pluck('id'))
            ->get();
    }

    public function loadCurrentLesson()
    {
        // Find the first incomplete lesson or the first lesson if all are complete
        $incompleteLessons = $this->lessons->filter(function ($lesson) {
            $progress = $this->userProgress->where('lesson_id', $lesson->id)->first();

            return ! $progress || ! $progress->completed;
        });

        if ($incompleteLessons->isNotEmpty()) {
            $this->currentLesson = $incompleteLessons->first();
        } else {
            // All lessons complete, show the last one
            $this->currentLesson = $this->lessons->last();
        }
    }

    public function selectLesson($lessonId)
    {
        $this->currentLesson = $this->lessons->find($lessonId);

        // Track that the user has started watching this lesson
        $this->trackLessonStart();
    }

    public function trackLessonStart()
    {
        if (! $this->currentLesson) {
            return;
        }

        UserLessonProgress::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'lesson_id' => $this->currentLesson->id,
            ],
            [
                'completed' => false,
                'watch_time_seconds' => 0,
            ]
        );
    }

    public function markComplete()
    {
        if (! $this->currentLesson) {
            return;
        }

        $progress = UserLessonProgress::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'lesson_id' => $this->currentLesson->id,
            ],
            [
                'completed' => true,
                'completed_at' => now(),
            ]
        );

        $this->loadProgress();
        $this->updateCourseProgress();

        // Check if this was the last lesson
        if ($this->checkCourseCompletion()) {
            $this->courseCompleted = true;
        }

        $this->showCompletionModal = true;
    }

    public function updateCourseProgress()
    {
        $totalLessons = $this->lessons->count();
        $completedLessons = $this->userProgress->where('completed', true)->count();

        $progressPercentage = $totalLessons > 0
            ? ($completedLessons / $totalLessons) * 100
            : 0;

        $this->enrollment->update([
            'progress_percentage' => $progressPercentage,
            'status' => $progressPercentage >= 100 ? 'completed' : 'active',
        ]);
    }

    public function checkCourseCompletion()
    {
        $totalLessons = $this->lessons->count();
        $completedLessons = $this->userProgress->where('completed', true)->count();

        return $completedLessons >= $totalLessons;
    }

    public function nextLesson()
    {
        $currentIndex = $this->lessons->search(fn ($l) => $l->id === $this->currentLesson->id);

        if ($currentIndex !== false && $currentIndex < $this->lessons->count() - 1) {
            $this->currentLesson = $this->lessons[$currentIndex + 1];
            $this->trackLessonStart();
        }
    }

    public function previousLesson()
    {
        $currentIndex = $this->lessons->search(fn ($l) => $l->id === $this->currentLesson->id);

        if ($currentIndex !== false && $currentIndex > 0) {
            $this->currentLesson = $this->lessons[$currentIndex - 1];
            $this->trackLessonStart();
        }
    }

    public function continueToNext()
    {
        $this->showCompletionModal = false;

        if (! $this->courseCompleted) {
            $this->nextLesson();
        }
    }

    public function updateWatchTime($watchTimeSeconds, $videoDuration = null)
    {
        if (! $this->currentLesson) {
            return;
        }

        // Calculate watch percentage if video duration is available
        $watchPercentage = 0;
        if ($videoDuration && $videoDuration > 0) {
            $watchPercentage = min(($watchTimeSeconds / $videoDuration) * 100, 100);
        } else {
            // Try to get duration from lesson metadata if not provided
            $metadata = $this->currentLesson->metadata ?? [];
            if (isset($metadata['duration']) && $metadata['duration'] > 0) {
                $watchPercentage = min(($watchTimeSeconds / $metadata['duration']) * 100, 100);
            }
        }

        $updateData = [
            'watch_time_seconds' => $watchTimeSeconds,
            'last_watched_at' => now(),
        ];

        // Only update watch_percentage if we can calculate it
        if ($watchPercentage > 0) {
            $updateData['watch_percentage'] = round($watchPercentage, 2);
        }

        UserLessonProgress::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'lesson_id' => $this->currentLesson->id,
            ],
            $updateData
        );

        $this->loadProgress();
    }

    public function autoCompleteLesson($watchTimeSeconds, $videoDuration)
    {
        if (! $this->currentLesson || ! $videoDuration) {
            return;
        }

        // Calculate watch percentage
        $watchPercentage = ($watchTimeSeconds / $videoDuration) * 100;

        // Auto-complete if 90% or more of video is watched
        if ($watchPercentage >= 90) {
            // First update watch time and percentage (this handles the progress tracking)
            $this->updateWatchTime($watchTimeSeconds, $videoDuration);
            
            // Then mark as complete
            UserLessonProgress::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'lesson_id' => $this->currentLesson->id,
                ],
                [
                    'completed' => true,
                    'completed_at' => now(),
                ]
            );

            $this->loadProgress();
            $this->updateCourseProgress();

            // Check if this was the last lesson
            if ($this->checkCourseCompletion()) {
                $this->courseCompleted = true;
            }

            // Show completion notification
            session()->flash('lesson_auto_completed', 'Lesson automatically completed based on watch time!');
            $this->showCompletionModal = true;
        } else {
            // Just update watch time with duration for percentage calculation
            $this->updateWatchTime($watchTimeSeconds, $videoDuration);
        }
    }

    #[Layout('components.layouts.student')]
    public function render()
    {
        return view('livewire.student.course-viewer');
    }
}
