<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = 'Students';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('email')->email()->required(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\Textarea::make('bio'),
                Forms\Components\Select::make('role')
                    ->options([
                        'student' => 'Student',
                        'admin' => 'Admin',
                    ])
                    ->default('student'),
                Forms\Components\Toggle::make('is_active')->default(true),

                Forms\Components\Section::make('Enrollments')
                    ->schema([
                        Forms\Components\Repeater::make('enrollments')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('course_id')
                                    ->relationship('course', 'title')
                                    ->required(),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'dropped' => 'Dropped',
                                    ])
                                    ->default('active'),
                            ])
                            ->addActionLabel('Add Course Enrollment'),
                    ])
                    ->visibleOn(['edit', 'view']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'primary' => 'admin',
                        'secondary' => 'student',
                    ]),
                Tables\Columns\TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Courses'),
                Tables\Columns\TextColumn::make('total_watch_time')
                    ->label('Watch Time')
                    ->getStateUsing(function ($record) {
                        $totalSeconds = $record->lessonProgress()->sum('watch_time_seconds');
                        $hours = floor($totalSeconds / 3600);
                        $minutes = floor(($totalSeconds % 3600) / 60);

                        return $hours.'h '.$minutes.'m';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withSum('lessonProgress', 'watch_time_seconds')
                            ->orderBy('lesson_progress_sum_watch_time_seconds', $direction);
                    }),
                Tables\Columns\TextColumn::make('completed_lessons_count')
                    ->label('Completed')
                    ->getStateUsing(function ($record) {
                        return $record->lessonProgress()->where('completed', true)->count();
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'student' => 'Student',
                        'admin' => 'Admin',
                    ]),
                TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
