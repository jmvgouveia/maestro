<?php

namespace App\Filament\Resources\UserBuildingAuthorizationResource\Pages;

use App\Filament\Resources\UserBuildingAuthorizationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserBuildingAuthorizations extends ListRecords
{
    protected static string $resource = UserBuildingAuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Adicionar autorização'),
        ];
    }
}
