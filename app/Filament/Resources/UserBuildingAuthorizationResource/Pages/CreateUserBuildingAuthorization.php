<?php

namespace App\Filament\Resources\UserBuildingAuthorizationResource\Pages;

use App\Filament\Resources\UserBuildingAuthorizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserBuildingAuthorization extends CreateRecord
{
    protected static string $resource = UserBuildingAuthorizationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
