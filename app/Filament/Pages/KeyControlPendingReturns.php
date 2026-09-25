<?php

namespace App\Filament\Pages;

use App\Models\KeyControl;
use App\Models\KeyControlEvent;
use App\Models\User;
use App\Models\UserBuildingAuthorization;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class KeyControlPendingReturns extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.key-control-pending-returns';

    protected static ?string $slug = 'devolucao-chaves-pendentes';

    protected static ?string $navigationGroup = 'Porteiro';

    protected static ?string $navigationLabel = 'Devoluções pendentes';

    protected static ?string $title = 'Devolução de chaves pendentes';

    protected static ?int $navigationSort = 2;

    public ?int $selectedKeyControlId = null;

    public ?array $returnData = [];

    protected function getForms(): array
    {
        return ['returnForm'];
    }

    public function mount(): void
    {
        $this->returnForm->fill();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view key control pending returns') ?? false;
    }

    public function getPendingKeysProperty(): Collection
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return KeyControl::query()
            ->with(['room.building', 'room.activeFloorKeyAccess', 'holder'])
            ->whereNull('returned_at')
            ->whereNotNull('room_released_at')
            ->where('is_corrected', false)
            ->whereHas('room.building.userBuildingAuthorizations', fn ($query) => $query->where('user_id', $user->getKey()))
            ->latest('picked_up_at')
            ->get();
    }

    public function returnForm(Form $form): Form
    {
        return $form->schema([
            Textarea::make('observations')
                ->label('Observações')
                ->maxLength(1000)
                ->columnSpanFull(),
        ])->statePath('returnData');
    }

    public function selectReturn(int $keyControlId): void
    {
        $this->selectedKeyControlId = $this->pendingKeys->firstWhere('id', $keyControlId)?->getKey();

        if ($this->selectedKeyControlId === null) {
            Notification::make()->title('Esta chave já não está pendente.')->danger()->send();

            return;
        }

        $this->returnForm->fill();
    }

    public function cancel(): void
    {
        $this->selectedKeyControlId = null;
        $this->returnForm->fill();
    }

    public function submitReturn(): void
    {
        $data = $this->returnForm->getState();
        $this->returnKey((int) $this->selectedKeyControlId, $data['observations'] ?? null);
        $this->cancel();
    }

    public function returnKey(int $keyControlId, ?string $observations = null): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        abort_unless($user->can('register key control pending return'), 403);

        $returned = DB::transaction(function () use ($keyControlId, $user, $observations): ?KeyControl {
            $keyControl = KeyControl::query()
                ->with('room')
                ->lockForUpdate()
                ->find($keyControlId);

            if (! $keyControl
                || $keyControl->returned_at !== null
                || $keyControl->is_corrected
                || ! UserBuildingAuthorization::query()
                    ->where('user_id', $user->getKey())
                    ->whereHas('building.rooms', fn ($query) => $query->whereKey($keyControl->room_id))
                    ->exists()) {
                return null;
            }

            $keyControl->forceFill([
                'returned_at' => now(),
                'returned_by' => $user->getKey(),
                'return_observations' => $keyControl->return_observations ?: ($observations ?: 'Devolução registada na página de pendentes.'),
            ])->save();

            KeyControlEvent::log(
                $keyControl->originalEventKeyControlId(),
                KeyControlEvent::KEY_RETURNED,
                $user->getKey(),
                ['return_observations' => $observations],
                null,
                $keyControl->returned_at
            );

            return $keyControl;
        });

        if (! $returned) {
            Notification::make()->title('A chave já foi devolvida ou não está autorizada.')->danger()->send();

            return;
        }

        Notification::make()->title('Devolução registada.')->success()->send();
    }
}
