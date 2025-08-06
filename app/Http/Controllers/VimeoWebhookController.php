<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VimeoWebhookController extends Controller
{
    /**
     * Handle Vimeo webhook notifications
     */
    public function handle(Request $request): Response
    {
        $signature = $request->header('X-Vimeo-Webhook-Signature');
        $payload = $request->getContent();
        
        // Verify webhook signature (if configured)
        if (!$this->verifySignature($signature, $payload)) {
            Log::warning('Invalid Vimeo webhook signature', [
                'signature' => $signature,
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }
        
        $data = $request->json()->all();
        
        Log::info('Received Vimeo webhook', [
            'event_type' => $data['event_type'] ?? 'unknown',
            'data' => $data,
        ]);
        
        $eventType = $data['event_type'] ?? null;
        
        switch ($eventType) {
            case 'video.upload.complete':
                $this->handleVideoUploadComplete($data);
                break;
            case 'video.transcode.complete':
                $this->handleVideoTranscodeComplete($data);
                break;
            case 'video.delete':
                $this->handleVideoDelete($data);
                break;
            default:
                Log::info('Unhandled Vimeo webhook event', ['event_type' => $eventType]);
        }
        
        return response('OK', 200);
    }
    
    /**
     * Handle video upload completion
     */
    private function handleVideoUploadComplete(array $data): void
    {
        $videoId = $this->extractVideoId($data);
        if (!$videoId) return;
        
        $lesson = Lesson::where('vimeo_video_id', $videoId)->first();
        if (!$lesson) {
            Log::warning('Lesson not found for Vimeo video', ['video_id' => $videoId]);
            return;
        }
        
        $lesson->update([
            'metadata' => array_merge($lesson->metadata ?? [], [
                'vimeo_status' => 'upload_complete',
                'upload_completed_at' => now()->toDateTimeString(),
                'webhook_data' => $data,
            ]),
        ]);
        
        Log::info('Video upload completed', [
            'lesson_id' => $lesson->id,
            'video_id' => $videoId,
        ]);
    }
    
    /**
     * Handle video transcode completion
     */
    private function handleVideoTranscodeComplete(array $data): void
    {
        $videoId = $this->extractVideoId($data);
        if (!$videoId) return;
        
        $lesson = Lesson::where('vimeo_video_id', $videoId)->first();
        if (!$lesson) {
            Log::warning('Lesson not found for Vimeo video', ['video_id' => $videoId]);
            return;
        }
        
        $lesson->update([
            'metadata' => array_merge($lesson->metadata ?? [], [
                'vimeo_status' => 'ready',
                'transcode_completed_at' => now()->toDateTimeString(),
                'duration' => $data['data']['duration'] ?? null,
                'webhook_data' => $data,
            ]),
        ]);
        
        Log::info('Video transcode completed', [
            'lesson_id' => $lesson->id,
            'video_id' => $videoId,
            'duration' => $data['data']['duration'] ?? null,
        ]);
    }
    
    /**
     * Handle video deletion
     */
    private function handleVideoDelete(array $data): void
    {
        $videoId = $this->extractVideoId($data);
        if (!$videoId) return;
        
        $lesson = Lesson::where('vimeo_video_id', $videoId)->first();
        if (!$lesson) {
            Log::warning('Lesson not found for deleted Vimeo video', ['video_id' => $videoId]);
            return;
        }
        
        $lesson->update([
            'vimeo_video_id' => null,
            'metadata' => array_merge($lesson->metadata ?? [], [
                'vimeo_status' => 'deleted',
                'deleted_at' => now()->toDateTimeString(),
                'webhook_data' => $data,
            ]),
        ]);
        
        Log::info('Video deleted from Vimeo', [
            'lesson_id' => $lesson->id,
            'video_id' => $videoId,
        ]);
    }
    
    /**
     * Extract video ID from webhook data
     */
    private function extractVideoId(array $data): ?string
    {
        $uri = $data['data']['uri'] ?? null;
        if (!$uri) return null;
        
        // URI format: /videos/123456789
        $parts = explode('/', $uri);
        return end($parts);
    }
    
    /**
     * Verify webhook signature
     */
    private function verifySignature(?string $signature, string $payload): bool
    {
        $webhookSecret = config('vimeo.webhook_secret');
        
        // If no webhook secret is configured, skip verification
        if (!$webhookSecret) {
            return true;
        }
        
        if (!$signature) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
}
