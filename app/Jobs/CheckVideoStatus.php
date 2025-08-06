<?php

namespace App\Jobs;

use App\Models\Lesson;
use App\Services\VimeoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckVideoStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = [60, 120, 300]; // 1 min, 2 min, 5 min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Lesson $lesson
    ) {
        $this->onQueue('video-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(VimeoService $vimeoService): void
    {
        if (! $this->lesson->vimeo_video_id) {
            Log::warning('No Vimeo video ID for lesson', ['lesson_id' => $this->lesson->id]);

            return;
        }

        $status = $vimeoService->getUploadStatus($this->lesson->vimeo_video_id);

        if (! $status) {
            Log::error('Failed to get video status', [
                'lesson_id' => $this->lesson->id,
                'vimeo_video_id' => $this->lesson->vimeo_video_id,
            ]);
            throw new \Exception('Failed to retrieve video status from Vimeo');
        }

        Log::info('Video status check', [
            'lesson_id' => $this->lesson->id,
            'status' => $status,
        ]);

        // Check if video is still processing
        if ($status['transcode_status'] === 'in_progress' ||
            $status['upload_status'] === 'in_progress') {
            // Check again in a few minutes
            dispatch(new CheckVideoStatus($this->lesson))
                ->delay(now()->addMinutes(3));

            return;
        }

        // Update lesson metadata if video is ready
        if ($status['is_playable'] && $status['transcode_status'] === 'complete') {
            $this->lesson->update([
                'metadata' => array_merge($this->lesson->metadata ?? [], [
                    'vimeo_status' => 'ready',
                    'duration' => $status['duration'],
                    'checked_at' => now()->toDateTimeString(),
                ]),
            ]);

            Log::info('Video is ready for playback', [
                'lesson_id' => $this->lesson->id,
                'duration' => $status['duration'],
            ]);
        } else {
            Log::warning('Video processing failed or incomplete', [
                'lesson_id' => $this->lesson->id,
                'status' => $status,
            ]);

            // Update lesson metadata with error status
            $this->lesson->update([
                'metadata' => array_merge($this->lesson->metadata ?? [], [
                    'vimeo_status' => 'error',
                    'error_details' => $status,
                    'checked_at' => now()->toDateTimeString(),
                ]),
            ]);
        }
    }
}
