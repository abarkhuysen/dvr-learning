<?php

namespace App\Filament\Resources\LessonResource\Pages;

use App\Filament\Resources\LessonResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->visible(fn () => $this->getRecord() && empty($this->getRecord()->vimeo_video_id))
                ->disabled(fn () => !$this->getRecord()),
        ];
    }
}
