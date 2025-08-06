<?php

namespace App\Filament\Resources\UserLessonProgressResource\Pages;

use App\Filament\Resources\UserLessonProgressResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserLessonProgress extends ListRecords
{
    protected static string $resource = UserLessonProgressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
