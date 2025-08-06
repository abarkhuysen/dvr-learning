<?php

namespace App\Filament\Resources\UserLessonProgressResource\Pages;

use App\Filament\Resources\UserLessonProgressResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserLessonProgress extends EditRecord
{
    protected static string $resource = UserLessonProgressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
