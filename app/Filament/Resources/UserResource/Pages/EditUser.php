<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    use \App\Filament\Resources\Concerns\RedirectsToList;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (User $record): bool => ! auth()->user()?->is($record)),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('is_active')) {
            return;
        }

        if ($this->record->is_active) {
            $this->record->forceFill([
                'activated_at' => now(),
                'activation_token' => null,
                'activation_token_expires_at' => null,
            ])->saveQuietly();

            return;
        }

        $this->record->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        DB::table('sessions')->where('user_id', $this->record->getKey())->delete();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('is_active', $data)
            && (bool) $data['is_active'] !== (bool) $this->record->is_active) {
            abort_unless(auth()->user()?->isSuperAdmin(), 403);
        }

        if (array_key_exists('mfa_required', $data)
            && (bool) $data['mfa_required'] !== (bool) $this->record->mfa_required) {
            abort_unless(auth()->user()?->isSuperAdmin(), 403);
        }

        if (array_key_exists('guardianStudents', $data)
            && ! (auth()->user()?->isSuperAdmin() || auth()->user()?->checkPermissionTo('update User'))) {
            abort(403, 'Não tem permissão para alterar os alunos associados.');
        }

        return $data;
    }

}
