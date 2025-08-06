<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Vimeo\Laravel\Facades\Vimeo;

class VimeoService
{
    /**
     * Upload a video to Vimeo
     */
    public function uploadVideo(string $filePath, array $metadata = []): array
    {
        try {
            // Default video settings
            $defaultMetadata = [
                'privacy' => [
                    'view' => 'unlisted',
                    'embed' => 'whitelist',
                    'download' => false,
                ],
                'embed' => [
                    'domains' => [config('app.url')],
                ],
            ];

            // Merge with provided metadata
            $videoData = array_merge($defaultMetadata, $metadata);

            // Upload video to Vimeo
            $response = Vimeo::upload($filePath, $videoData);

            // Extract video ID from response
            $videoId = $this->extractVideoId($response);

            Log::info('Video uploaded to Vimeo', [
                'video_id' => $videoId,
                'response' => $response,
            ]);

            return [
                'success' => true,
                'video_id' => $videoId,
                'uri' => $response,
            ];

        } catch (\Exception $e) {
            Log::error('Vimeo upload failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update video metadata on Vimeo
     */
    public function updateVideo(string $videoId, array $metadata): bool
    {
        try {
            $response = Vimeo::request("/videos/{$videoId}", $metadata, 'PATCH');

            return $response['status'] === 200;
        } catch (\Exception $e) {
            Log::error('Vimeo update failed', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return false;
        }
    }

    /**
     * Delete a video from Vimeo
     */
    public function deleteVideo(string $videoId): bool
    {
        try {
            $response = Vimeo::request("/videos/{$videoId}", [], 'DELETE');

            return $response['status'] === 204;
        } catch (\Exception $e) {
            Log::error('Vimeo delete failed', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return false;
        }
    }

    /**
     * Get video information from Vimeo
     */
    public function getVideo(string $videoId): ?array
    {
        try {
            $response = Vimeo::request("/videos/{$videoId}");

            if ($response['status'] === 200) {
                return $response['body'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Vimeo get video failed', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return null;
        }
    }

    /**
     * Generate embed code for a video
     */
    public function getEmbedCode(string $videoId, array $options = []): string
    {
        $defaultOptions = [
            'width' => '100%',
            'height' => '100%',
            'frameborder' => '0',
            'allow' => 'autoplay; fullscreen; picture-in-picture',
            'title' => 'Video',
        ];

        $options = array_merge($defaultOptions, $options);

        $attributes = array_map(
            fn ($key, $value) => "{$key}=\"{$value}\"",
            array_keys($options),
            array_values($options)
        );

        return sprintf(
            '<iframe src="https://player.vimeo.com/video/%s?badge=0&autopause=0&player_id=0&app_id=58479" %s></iframe>',
            $videoId,
            implode(' ', $attributes)
        );
    }

    /**
     * Extract video ID from Vimeo URI
     */
    private function extractVideoId(string $uri): string
    {
        // URI format is typically /videos/123456789
        $parts = explode('/', $uri);

        return end($parts);
    }

    /**
     * Set domain restrictions for a video
     */
    public function setDomainRestrictions(string $videoId, array $domains): bool
    {
        return $this->updateVideo($videoId, [
            'privacy' => [
                'embed' => 'whitelist',
            ],
            'embed' => [
                'domains' => $domains,
            ],
        ]);
    }

    /**
     * Get upload status for a video
     */
    public function getUploadStatus(string $videoId): ?array
    {
        $video = $this->getVideo($videoId);

        if (! $video) {
            return null;
        }

        return [
            'status' => $video['status'] ?? 'unknown',
            'upload_status' => $video['upload']['status'] ?? 'unknown',
            'transcode_status' => $video['transcode']['status'] ?? 'unknown',
            'duration' => $video['duration'] ?? 0,
            'is_playable' => isset($video['is_playable']) ? $video['is_playable'] : false,
        ];
    }
}
