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
use Illuminate\Support\Facades\Storage;

class ProcessVideoUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $maxExceptions = 1;

    public $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Lesson $lesson,
        public string $videoPath,
        public ?string $title = null,
        public ?string $description = null
    ) {
        $this->onQueue('video-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(VimeoService $vimeoService): void
    {
        try {
            Log::info('Starting video upload for lesson', [
                'lesson_id' => $this->lesson->id,
                'video_path' => $this->videoPath,
            ]);

            // Prepare metadata
            $metadata = [
                'name' => $this->title ?? $this->lesson->title,
                'description' => $this->description ?? $this->lesson->description,
                'privacy' => [
                    'view' => 'unlisted',
                    'embed' => 'whitelist',
                    'download' => false,
                ],
                'embed' => [
                    'domains' => [config('app.url')],
                ],
            ];

            // Upload video to Vimeo
            $result = $vimeoService->uploadVideo($this->videoPath, $metadata);

            if ($result['success']) {
                // Update lesson with Vimeo video ID
                $this->lesson->update([
                    'vimeo_video_id' => $result['video_id'],
                ]);

                Log::info('Video uploaded successfully', [
                    'lesson_id' => $this->lesson->id,
                    'vimeo_video_id' => $result['video_id'],
                ]);

                // Dispatch job to check upload status
                dispatch(new CheckVideoStatus($this->lesson))
                    ->delay(now()->addMinutes(2));

                // Clean up temporary file if it exists
                if (Storage::exists($this->videoPath)) {
                    Storage::delete($this->videoPath);
                }
            } else {
                Log::error('Video upload failed', [
                    'lesson_id' => $this->lesson->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                throw new \Exception('Failed to upload video: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Video upload job failed', [
                'lesson_id' => $this->lesson->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clean up temporary file on failure
            if (Storage::exists($this->videoPath)) {
                Storage::delete($this->videoPath);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Video upload job permanently failed', [
            'lesson_id' => $this->lesson->id,
            'error' => $exception->getMessage(),
        ]);

        // Clean up temporary file
        if (Storage::exists($this->videoPath)) {
            Storage::delete($this->videoPath);
        }

        // Optionally notify admin or update lesson status
    }
}
