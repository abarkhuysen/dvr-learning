<?php

namespace App\Filament\Resources\LessonResource\Pages;

use App\Filament\Resources\LessonResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditLesson extends EditRecord
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Upload video action (only show if no video exists)
            Actions\Action::make('upload_video')
                ->label('Upload Video')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    Forms\Components\FileUpload::make('video')
                        ->label('Video File')
                        ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi'])
                        ->maxSize(5120 * 1024) // 5GB
                        ->directory('temp-videos')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    $videoPath = $data['video'];

                    // Dispatch job to upload video to Vimeo
                    dispatch(new \App\Jobs\ProcessVideoUpload(
                        $record,
                        $videoPath,
                        $record->title,
                        $record->description
                    ));

                    \Filament\Notifications\Notification::make()
                        ->title('Video upload started')
                        ->body('The video is being uploaded to Vimeo. This may take a few minutes.')
                        ->success()
                        ->send();
                })
                ->visible(fn () => empty($this->getRecord()->vimeo_video_id)),

            // View video action (only show if video exists)
            Actions\Action::make('view_video')
                ->label('View Video')
                ->icon('heroicon-o-play')
                ->color('success')
                ->url(fn () => "https://vimeo.com/{$this->getRecord()->vimeo_video_id}")
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->getRecord()->vimeo_video_id)),

            // Remove video action (only show if video exists)
            Actions\Action::make('remove_video')
                ->label('Remove Video')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Remove Video')
                ->modalDescription('Are you sure you want to remove this video from Vimeo? This action cannot be undone.')
                ->modalSubmitActionLabel('Remove Video')
                ->action(function (): void {
                    $record = $this->getRecord();

                    // Remove video from Vimeo
                    $vimeoService = app(\App\Services\VimeoService::class);
                    $vimeoService->deleteVideo($record->vimeo_video_id);

                    // Clear video ID from lesson
                    $record->update([
                        'vimeo_video_id' => null,
                        'metadata' => array_merge($record->metadata ?? [], [
                            'video_removed_at' => now()->toDateTimeString(),
                        ]),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Video removed')
                        ->body('The video has been removed from Vimeo.')
                        ->success()
                        ->send();
                })
                ->visible(fn () => !empty($this->getRecord()->vimeo_video_id)),

            Actions\DeleteAction::make(),
        ];
    }
}
