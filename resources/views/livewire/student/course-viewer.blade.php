<div class="min-h-screen bg-gray-50">
    <!-- Course Header -->
    <header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between py-4">
                <div class="flex items-center space-x-4">
                    <flux:button href="/dashboard" wire:navigate variant="ghost" icon="arrow-left">
                        Back to Dashboard
                    </flux:button>
                    <div>
                        <flux:heading size="lg">{{ $course->title }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">
                            {{ $userProgress->where('completed', true)->count() }} / {{ $lessons->count() }} lessons completed
                        </flux:text>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="flex items-center space-x-4">
                    <div class="w-48">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full transition-all duration-300" 
                                 style="width: {{ $enrollment->progress_percentage }}%"></div>
                        </div>
                    </div>
                    <flux:text size="sm" class="font-semibold">
                        {{ number_format($enrollment->progress_percentage, 0) }}%
                    </flux:text>
                </div>
            </div>
        </div>
    </header>

    <div class="flex h-[calc(100vh-73px)]">
        <!-- Sidebar - Lesson List -->
        <aside class="w-80 bg-white border-r overflow-y-auto">
            <div class="p-4">
                <flux:heading size="md" class="mb-4">Course Content</flux:heading>
                
                <div class="space-y-2">
                    @foreach($lessons as $lesson)
                        <button 
                            wire:click="selectLesson({{ $lesson->id }})"
                            class="w-full text-left border rounded-lg p-3 transition-all hover:shadow-sm
                                   {{ $currentLesson?->id === $lesson->id 
                                       ? 'bg-blue-50 border-blue-300 shadow-sm' 
                                       : 'hover:bg-gray-50 border-gray-200' }}">
                            <div class="flex items-start space-x-3">
                                <div class="mt-0.5">
                                    @if($userProgress->where('lesson_id', $lesson->id)->first()?->completed)
                                        <flux:icon name="check-circle" class="size-5 text-green-500" />
                                    @elseif($currentLesson?->id === $lesson->id)
                                        <flux:icon name="play-circle" class="size-5 text-blue-500" />
                                    @else
                                        <flux:icon name="play-circle" class="size-5 text-gray-400" />
                                    @endif
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <flux:text class="font-medium {{ $currentLesson?->id === $lesson->id ? 'text-blue-900' : '' }}">
                                            {{ $lesson->title }}
                                        </flux:text>
                                        @if($lesson->is_free)
                                            <flux:badge color="green" size="sm">Free</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text size="sm" class="text-gray-500">
                                        Lesson {{ $lesson->order }}
                                    </flux:text>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto">
            @if($currentLesson)
                <div class="p-6 max-w-5xl mx-auto">
                    <!-- Video Player -->
                    <div class="bg-black rounded-lg overflow-hidden mb-6" style="aspect-ratio: 16/9;">
                        @if($currentLesson->vimeo_video_id)
                            <iframe 
                                id="vimeo-player-{{ $currentLesson->id }}"
                                src="https://player.vimeo.com/video/{{ $currentLesson->vimeo_video_id }}?badge=0&autopause=0&player_id=vimeo-player-{{ $currentLesson->id }}"
                                class="w-full h-full"
                                frameborder="0"
                                allow="autoplay; fullscreen; picture-in-picture"
                                title="{{ $currentLesson->title }}"
                                data-lesson-id="{{ $currentLesson->id }}">
                            </iframe>
                        @else
                            <div class="flex items-center justify-center h-full">
                                <div class="text-center">
                                    <flux:icon name="video-camera-slash" class="size-16 text-gray-400 mx-auto mb-4" />
                                    <flux:text class="text-gray-400">Video not available</flux:text>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Real-time Progress Indicator -->
                    @if($currentLesson->vimeo_video_id)
                    <div class="bg-white rounded-lg shadow-sm p-4 mb-6" id="video-progress-indicator">
                        <div class="flex items-center justify-between mb-2">
                            <flux:text size="sm" class="font-medium">Video Progress</flux:text>
                            <span id="watch-time-display" class="text-sm text-gray-600">0:00 / 0:00</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="video-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <flux:text size="xs" class="text-gray-500">Watch 90% to auto-complete</flux:text>
                            <span id="watch-percentage-display" class="text-xs text-gray-500">0%</span>
                        </div>
                    </div>
                    @endif

                    <!-- Lesson Info and Actions -->
                    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <flux:heading size="xl" class="mb-2">{{ $currentLesson->title }}</flux:heading>
                                @if($currentLesson->description)
                                    <flux:text class="text-gray-600">{{ $currentLesson->description }}</flux:text>
                                @endif
                            </div>
                            
                            <div class="ml-6">
                                @if(!$userProgress->where('lesson_id', $currentLesson->id)->first()?->completed)
                                    <flux:button 
                                        wire:click="markComplete" 
                                        variant="primary" 
                                        icon="check">
                                        Mark as Complete
                                    </flux:button>
                                @else
                                    <div class="flex items-center space-x-2 text-green-600">
                                        <flux:icon name="check-circle" class="size-6" />
                                        <span class="font-semibold">Completed</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="flex justify-between items-center pt-4 border-t">
                            @php
                                $currentIndex = $lessons->search(fn($l) => $l->id === $currentLesson->id);
                                $hasPrevious = $currentIndex > 0;
                                $hasNext = $currentIndex < $lessons->count() - 1;
                            @endphp
                            
                            @if($hasPrevious)
                                <flux:button 
                                    wire:click="previousLesson" 
                                    variant="outline" 
                                    icon="chevron-left">
                                    Previous Lesson
                                </flux:button>
                            @else
                                <div></div>
                            @endif
                            
                            @if($hasNext)
                                <flux:button 
                                    wire:click="nextLesson" 
                                    variant="primary">
                                    Next Lesson
                                    <flux:icon name="chevron-right" class="ml-2" />
                                </flux:button>
                            @else
                                @if($userProgress->where('completed', true)->count() === $lessons->count())
                                    <flux:badge color="green" class="px-4 py-2">
                                        <flux:icon name="academic-cap" class="mr-2" />
                                        Course Completed!
                                    </flux:badge>
                                @else
                                    <div></div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- Additional Lesson Resources (if needed) -->
                    @if($currentLesson->resources || $currentLesson->notes)
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <flux:heading size="lg" class="mb-4">Lesson Resources</flux:heading>
                            <!-- Add resources content here -->
                        </div>
                    @endif
                </div>
            @else
                <div class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <flux:icon name="academic-cap" class="size-16 text-gray-400 mx-auto mb-4" />
                        <flux:text size="lg" class="text-gray-500">Select a lesson to start learning</flux:text>
                    </div>
                </div>
            @endif
        </main>
    </div>

    <!-- Vimeo Player API and Video Tracking Script -->
    @if($currentLesson && $currentLesson->vimeo_video_id)
    <script src="https://player.vimeo.com/api/player.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.querySelector('#vimeo-player-{{ $currentLesson->id }}');
            
            if (!iframe) return;
            
            const player = new Vimeo.Player(iframe);
            const lessonId = {{ $currentLesson->id }};
            let videoDuration = 0;
            let lastWatchTime = 0;
            let hasAutoCompleted = false;
            let watchTimeUpdateInterval;
            
            // Get video duration
            player.getDuration().then(function(duration) {
                videoDuration = duration;
                document.getElementById('watch-time-display').textContent = '0:00 / ' + formatTime(duration);
            });
            
            // Update progress display
            function updateProgressDisplay(currentTime) {
                const percentage = videoDuration > 0 ? (currentTime / videoDuration) * 100 : 0;
                const progressBar = document.getElementById('video-progress-bar');
                const percentageDisplay = document.getElementById('watch-percentage-display');
                const timeDisplay = document.getElementById('watch-time-display');
                
                if (progressBar) progressBar.style.width = percentage + '%';
                if (percentageDisplay) percentageDisplay.textContent = Math.round(percentage) + '%';
                if (timeDisplay) timeDisplay.textContent = formatTime(currentTime) + ' / ' + formatTime(videoDuration);
            }
            
            // Format time in MM:SS
            function formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return minutes + ':' + (secs < 10 ? '0' : '') + secs;
            }
            
            // Track time updates
            player.on('timeupdate', function(data) {
                const currentTime = data.seconds;
                lastWatchTime = Math.max(lastWatchTime, currentTime); // Only increase, never decrease
                updateProgressDisplay(currentTime);
                
                // Check for auto-completion (90% threshold)
                if (!hasAutoCompleted && videoDuration > 0) {
                    const watchPercentage = (lastWatchTime / videoDuration) * 100;
                    if (watchPercentage >= 90) {
                        hasAutoCompleted = true;
                        @this.autoCompleteLesson(lastWatchTime, videoDuration);
                    }
                }
            });
            
            // Update watch time every 10 seconds
            watchTimeUpdateInterval = setInterval(function() {
                if (lastWatchTime > 0) {
                    @this.updateWatchTime(lastWatchTime);
                }
            }, 10000);
            
            // Handle play event
            player.on('play', function() {
                // Track lesson start when video starts playing
                @this.trackLessonStart();
            });
            
            // Final update when video ends or user navigates away
            player.on('ended', function() {
                clearInterval(watchTimeUpdateInterval);
                if (lastWatchTime > 0) {
                    @this.updateWatchTime(lastWatchTime);
                }
            });
            
            // Clean up on navigation
            window.addEventListener('beforeunload', function() {
                clearInterval(watchTimeUpdateInterval);
                if (lastWatchTime > 0) {
                    @this.updateWatchTime(lastWatchTime);
                }
            });
        });
    </script>
    @endif

    <!-- Lesson Completion Modal -->
    <flux:modal name="lesson-completion" :show="$showCompletionModal" wire:model="showCompletionModal">
        <div class="p-6">
            <div class="text-center">
                @if($courseCompleted)
                    <flux:icon name="academic-cap" class="size-20 text-green-500 mx-auto mb-4" />
                    <flux:heading size="xl" class="mb-2">Course Completed! 🎉</flux:heading>
                    <flux:text class="text-gray-600 mb-6">
                        Congratulations! You've successfully completed all lessons in {{ $course->title }}.
                    </flux:text>
                    <div class="flex justify-center space-x-3">
                        <flux:button wire:click="$set('showCompletionModal', false)" variant="outline">
                            Review Lessons
                        </flux:button>
                        <flux:button href="/dashboard" wire:navigate variant="primary">
                            Back to Dashboard
                        </flux:button>
                    </div>
                @else
                    <flux:icon name="check-circle" class="size-20 text-green-500 mx-auto mb-4" />
                    <flux:heading size="xl" class="mb-2">Lesson Complete!</flux:heading>
                    <flux:text class="text-gray-600 mb-6">
                        Great job! You've completed "{{ $currentLesson?->title }}".
                    </flux:text>
                    <div class="flex justify-center space-x-3">
                        <flux:button wire:click="$set('showCompletionModal', false)" variant="outline">
                            Stay on This Lesson
                        </flux:button>
                        @php
                            $currentIdx = $lessons->search(fn($l) => $l->id === $currentLesson?->id);
                            $hasNextLesson = $currentIdx !== false && $currentIdx < $lessons->count() - 1;
                        @endphp
                        @if($hasNextLesson)
                            <flux:button wire:click="continueToNext" variant="primary">
                                Continue to Next Lesson
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </flux:modal>
</div>