<?php

namespace App\Filament\Resources\EmailAuditResource\Pages;

use App\Filament\Resources\EmailAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailAudits extends ListRecords
{
    protected static string $resource = EmailAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
