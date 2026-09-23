<?php

namespace App\Filament\Resources\UserBuildingAuthorizationResource\Pages;

use App\Filament\Resources\UserBuildingAuthorizationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserBuildingAuthorization extends EditRecord
{
    protected static string $resource = UserBuildingAuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
