<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserLessonProgressResource\Pages;
use App\Models\UserLessonProgress;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserLessonProgressResource extends Resource
{
    protected static ?string $model = UserLessonProgress::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Learning Progress';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('lesson_id')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('watch_time_seconds')
                    ->numeric()
                    ->label('Watch Time (seconds)')
                    ->default(0),
                Forms\Components\TextInput::make('watch_percentage')
                    ->numeric()
                    ->label('Watch Percentage')
                    ->suffix('%')
                    ->default(0),
                Forms\Components\Toggle::make('completed'),
                Forms\Components\DateTimePicker::make('completed_at'),
                Forms\Components\DateTimePicker::make('last_watched_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Student'),
                Tables\Columns\TextColumn::make('lesson.title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->label('Lesson'),
                Tables\Columns\TextColumn::make('lesson.course.title')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->label('Course'),
                Tables\Columns\TextColumn::make('watch_time_formatted')
                    ->label('Watch Time')
                    ->getStateUsing(function ($record) {
                        $seconds = $record->watch_time_seconds;
                        $hours = floor($seconds / 3600);
                        $minutes = floor(($seconds % 3600) / 60);
                        $remainingSeconds = $seconds % 60;

                        if ($hours > 0) {
                            return $hours.'h '.$minutes.'m '.$remainingSeconds.'s';
                        }
                        if ($minutes > 0) {
                            return $minutes.'m '.$remainingSeconds.'s';
                        }

                        return $remainingSeconds.'s';
                    }),
                Tables\Columns\TextColumn::make('watch_percentage')
                    ->suffix('%')
                    ->sortable()
                    ->label('% Watched')
                    ->color(fn ($state) => $state >= 90 ? 'success' : ($state >= 50 ? 'warning' : 'danger')),
                Tables\Columns\IconColumn::make('completed')
                    ->boolean()
                    ->label('Completed'),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Completed At')
                    ->placeholder('Not completed'),
                Tables\Columns\TextColumn::make('last_watched_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Watched')
                    ->since(),
            ])
            ->filters([
                TernaryFilter::make('completed'),
                SelectFilter::make('lesson.course_id')
                    ->relationship('lesson.course', 'title')
                    ->label('Course'),
                Tables\Filters\Filter::make('high_engagement')
                    ->label('High Engagement (>90%)')
                    ->query(fn (Builder $query): Builder => $query->where('watch_percentage', '>=', 90)),
                Tables\Filters\Filter::make('recent_activity')
                    ->label('Recent Activity (7 days)')
                    ->query(fn (Builder $query): Builder => $query->where('last_watched_at', '>=', now()->subDays(7))
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('last_watched_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserLessonProgresses::route('/'),
            'create' => Pages\CreateUserLessonProgress::route('/create'),
            'view' => Pages\ViewUserLessonProgress::route('/{record}'),
            'edit' => Pages\EditUserLessonProgress::route('/{record}/edit'),
        ];
    }
}
