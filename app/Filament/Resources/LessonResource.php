<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LessonResource\Pages;
use App\Filament\Resources\LessonResource\RelationManagers;
use App\Models\Lesson;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Courses';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lesson Information')
                    ->schema([
                        Forms\Components\Select::make('course_id')
                            ->relationship('course', 'title')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                    ])->columns(1),
                    
                Forms\Components\Section::make('Video Settings')
                    ->schema([
                        Forms\Components\TextInput::make('vimeo_video_id')
                            ->label('Vimeo Video ID')
                            ->helperText('Enter the Vimeo video ID (numbers only)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('order')
                            ->label('Lesson Order')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        Forms\Components\Toggle::make('is_free')
                            ->label('Free Preview')
                            ->helperText('Allow students to watch this lesson without enrollment'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('vimeo_video_id')
                    ->label('Has Video')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => !empty($record->vimeo_video_id)),
                Tables\Columns\IconColumn::make('is_free')
                    ->label('Free')
                    ->boolean(),
                Tables\Columns\TextColumn::make('userProgress_count')
                    ->counts('userProgress')
                    ->label('Views'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->relationship('course', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('is_free')
                    ->label('Free Preview')
                    ->options([
                        '1' => 'Free',
                        '0' => 'Paid',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('upload_video')
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
                    ->action(function (array $data, Lesson $record): void {
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
                    ->visible(fn (Lesson $record) => empty($record->vimeo_video_id)),
                Tables\Actions\Action::make('view_video')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn (Lesson $record) => "https://vimeo.com/{$record->vimeo_video_id}")
                    ->openUrlInNewTab()
                    ->visible(fn (Lesson $record) => !empty($record->vimeo_video_id)),
                Tables\Actions\Action::make('remove_video')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Lesson $record): void {
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
                    ->visible(fn (Lesson $record) => !empty($record->vimeo_video_id)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('course_id')
            ->defaultSort('order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessons::route('/'),
            'create' => Pages\CreateLesson::route('/create'),
            'edit' => Pages\EditLesson::route('/{record}/edit'),
        ];
    }
}
