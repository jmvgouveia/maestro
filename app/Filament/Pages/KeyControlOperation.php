<?php

namespace App\Filament\Pages;

use App\Models\KeyControl;
use App\Models\KeyControlFloorKeyAccess;
use App\Models\KeyControlEvent;
use App\Models\Building;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserBuildingAuthorization;
use App\Services\KeyControlClosureService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class KeyControlOperation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.key-control-operation';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Porteiro';

    protected static ?string $navigationLabel = 'Salas';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Controlo de Chaves';

    protected static ?string $slug = 'operacao-chaves';

    public string $search = '';

    public ?int $selectedBuildingId = null;

    public ?string $selectedStatus = null;

    public string $roomSort = 'asc';

    public ?int $selectedRoomId = null;

    public ?string $mode = null;

    public ?array $pickUpData = [];

    public ?array $returnData = [];

    public ?array $correctData = [];

    public function mount(): void
    {
        $this->pickUpForm->fill();
        $this->returnForm->fill();
        $this->correctForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'pickUpForm',
            'returnForm',
            'correctForm',
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isPorter() ?? false;
    }

    public function getRoomsProperty(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        $direction = in_array($this->roomSort, ['asc', 'desc'], true) ? $this->roomSort : 'asc';

        return $this->filteredRoomsQuery()
            ->when($this->selectedStatus === 'available', fn ($query) => $query->whereDoesntHave('activeKeyControl')->whereDoesntHave('activeFloorKeyAccess'))
            ->when($this->selectedStatus === 'occupied', fn ($query) => $query->where(fn ($query) => $query->whereHas('activeKeyControl')->orWhereHas('activeFloorKeyAccess')))
            ->when($this->search !== '', function ($query): void {
                $search = mb_strtolower($this->search);
                $query->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                    ->orWhereHas('building', function ($q) use ($search): void {
                        $q->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(address) like ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('activeKeyControl', function ($query) use ($search): void {
                        $query->whereHasMorph('holder', [Teacher::class, Student::class], function ($holderQuery) use ($search): void {
                            $holderQuery
                                ->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                                ->orWhereRaw('LOWER(COALESCE(number, \'\')) like ?', ["%{$search}%"]);
                        });
                    });
                    $query->orWhereHas('activeFloorKeyAccess.occupant', function ($occupantQuery) use ($search): void {
                        $occupantQuery->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(COALESCE(number, \'\')) like ?', ["%{$search}%"]);
                    });
                    $query->orWhereHas('features', function ($featureQuery) use ($search): void {
                        $featureQuery->whereRaw('LOWER(name) like ?', ["%{$search}%"]);
                    });
                });
            })
            ->orderBy('name', $direction)
            ->get();
    }

    public function getCorrectionRoomsProperty(): Collection
    {
        return $this->authorizedRoomsQuery()
            ->orderBy('name')
            ->get();
    }

    public function getBuildingsProperty(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return Building::query()
            ->whereHas('userBuildingAuthorizations', fn ($query) => $query->where('user_id', $user->getKey()))
            ->orderBy('address')
            ->get();
    }

    public function getOccupiedRoomsCountProperty(): int
    {
        return (int) $this->filteredRoomsQuery()
            ->where(fn ($query) => $query->whereHas('activeKeyControl')->orWhereHas('activeFloorKeyAccess'))
            ->count();
    }

    public function getAvailableRoomsCountProperty(): int
    {
        return max(0, $this->authorizedRoomsCount - $this->occupiedRoomsCount);
    }

    public function getAuthorizedRoomsCountProperty(): int
    {
        return (int) $this->filteredRoomsQuery()->count();
    }

    private function authorizedRoomsQuery()
    {
        /** @var User $user */
        $user = auth()->user();

        return Room::query()
            ->with(['building', 'features', 'activeKeyControl.holder', 'activeFloorKeyAccess.occupant', 'pendingKeyControls.holder'])
            ->whereHas('building.userBuildingAuthorizations', fn ($query) => $query->where('user_id', $user->getKey()));
    }

    private function filteredRoomsQuery()
    {
        return $this->authorizedRoomsQuery()
            ->when($this->selectedBuildingId, fn ($query) => $query->where('id_building', $this->selectedBuildingId));
    }

    public function activeKeyControlFor(Room $room): ?KeyControl
    {
        return $room->activeKeyControl;
    }

    public function activeFloorKeyAccessFor(Room $room): ?KeyControlFloorKeyAccess
    {
        return $room->activeFloorKeyAccess;
    }

    public function getSelectedFloorAccessProperty(): ?KeyControlFloorKeyAccess
    {
        return $this->rooms->firstWhere('id', $this->selectedRoomId)?->activeFloorKeyAccess;
    }

    public function pickUpForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('holder')
                    ->label('Entregue a')
                    ->required()
                    ->searchable()
                    ->autofocus()
                    ->options(fn (): array => $this->holderOptions())
                    ->columnSpanFull(),
                Textarea::make('observations')
                    ->label('Observações')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('pickUpData');
    }

    public function returnForm(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('observations')
                    ->label('Observações')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('returnData');
    }

    public function correctForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('room_id')
                    ->label('Sala correta')
                    ->options(fn () => $this->correctionRooms->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->dehydrated(),
                Select::make('holder')
                    ->label('Pessoa correta')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => $this->holderOptions())
                    ->columnSpanFull(),
                Textarea::make('pick_up_observations')
                    ->label('Observações no levantamento')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Textarea::make('return_observations')
                    ->label('Observações na devolução')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Motivo da correção')
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('correctData');
    }

    public function selectPickUp(int $roomId): void
    {
        if (! $this->isAuthorizedRoom($roomId)) {
            $this->sendError('Não tem autorização para operar esta sala.');

            return;
        }

        $this->selectedRoomId = $roomId;
        $this->mode = 'pickUp';
        $this->pickUpForm->fill(['room_id' => $roomId]);
        $this->returnForm->fill();
        $this->correctForm->fill();
    }

    public function selectReturn(int $roomId): void
    {
        if (! $this->isAuthorizedRoom($roomId)) {
            $this->sendError('Não tem autorização para operar esta sala.');

            return;
        }

        $this->selectedRoomId = $roomId;
        $this->mode = 'return';
        $this->returnForm->fill(['room_id' => $roomId]);
        $this->pickUpForm->fill();
        $this->correctForm->fill();
    }

    public function selectCorrect(int $roomId): void
    {
        if (! $this->isAuthorizedRoom($roomId)) {
            $this->sendError('Não tem autorização para operar esta sala.');

            return;
        }

        $this->selectedRoomId = $roomId;
        $floorAccess = $this->getAuthorizedRoom($roomId)?->activeFloorKeyAccess;
        $this->mode = $floorAccess ? 'floorCorrect' : 'correct';

        if ($floorAccess) {
            $this->correctForm->fill([
                'room_id' => $roomId,
                'holder' => Teacher::class.':'.$floorAccess->occupant_id,
            ]);
            $this->pickUpForm->fill();
            $this->returnForm->fill();

            return;
        }

        $latest = KeyControl::query()
            ->where('room_id', $roomId)
            ->where('is_corrected', false)
            ->latest('created_at')
            ->first();

        $this->correctForm->fill([
            'room_id' => $roomId,
            'holder' => $latest ? $latest->holder_type.':'.$latest->holder_id : null,
            'pick_up_observations' => $latest?->pick_up_observations,
            'return_observations' => $latest?->return_observations,
        ]);
        $this->pickUpForm->fill();
        $this->returnForm->fill();
    }

    public function cancel(): void
    {
        $this->selectedRoomId = null;
        $this->mode = null;
        unset($this->rooms, $this->selectedFloorAccess);
        $this->pickUpForm->fill();
        $this->returnForm->fill();
        $this->correctForm->fill();
    }

    public function refreshRoomState(): void
    {
        unset($this->rooms, $this->selectedFloorAccess);
    }

    public function submitPickUp(): void
    {
        $data = $this->pickUpForm->getState();
        $holder = $this->parseHolderSelection($data['holder'] ?? null);

        if ($holder === null) {
            $this->sendError('Selecione um utilizador válido.');

            return;
        }

        $this->pickUp(
            (int) $this->selectedRoomId,
            $holder['type'],
            $holder['id'],
            $data['observations'] ?? null
        );

        $this->cancel();
    }

    public function openWithFloorKeyFromModal(): void
    {
        $data = $this->pickUpForm->getState();
        $holder = $this->parseHolderSelection($data['holder'] ?? null);

        if ($holder === null || $holder['type'] !== Teacher::class) {
            $this->sendError('Selecione um professor para abrir a sala com a chave do funcionário de piso.');

            return;
        }

        if ($this->openWithFloorKey((int) $this->selectedRoomId, $holder['id'])) {
            $this->cancel();
        }
    }

    public function submitReturn(): void
    {
        $data = $this->returnForm->getState();

        $this->returnKey((int) $this->selectedRoomId, $data['observations'] ?? null);

        $this->cancel();
    }

    public function submitEndFloorKeyUse(): void
    {
        $access = $this->selectedFloorAccess;

        if ($access === null) {
            $this->sendError('Esta sala não está ocupada através da chave do funcionário de piso.');

            return;
        }

        try {
            app(KeyControlClosureService::class)->endFloorKeyAccess($access, auth()->user());
            $this->sendSuccess('Utilização da sala terminada.');
            $this->cancel();
        } catch (\RuntimeException $exception) {
            $this->sendError($exception->getMessage());
        }
    }

    public function releaseRoom(int $roomId): void
    {
        $closureService = app(KeyControlClosureService::class);
        $room = $this->getAuthorizedRoom($roomId);
        $active = $room?->activeKeyControl;

        if ($room === null || $active === null) {
            $this->sendError('Esta sala não tem uma chave operacional para libertar.');

            return;
        }

        if ($active->holder_type !== Teacher::class) {
            $this->sendError('Esta ação só está disponível para chaves entregues a professores.');

            return;
        }

        try {
            $closureService->releaseRoom($active, auth()->user());
            $this->sendSuccess('Sala libertada. A chave continua pendente com o professor.');
            $this->cancel();
        } catch (\RuntimeException $exception) {
            $this->sendError($exception->getMessage());
        }
    }

    public function openWithFloorKey(int $roomId, ?int $occupantId = null): bool
    {
        $closureService = app(KeyControlClosureService::class);
        $room = $this->getAuthorizedRoom($roomId);
        $pending = $room?->pendingKeyControls()
            ->whereNotNull('room_released_at')
            ->first();

        if ($room === null || $pending === null) {
            $this->sendError('Não existe uma chave pendente associada a esta sala.');

            return false;
        }

        $occupant = $occupantId === null && $pending->holder_type === Teacher::class
            ? $pending->holder
            : Teacher::find($occupantId);

        if ($occupant === null) {
            $this->sendError('Selecione um professor válido.');

            return false;
        }

        try {
            $closureService->recordFloorKeyAccess($pending, auth()->user(), $occupant);
            $this->sendSuccess('Abertura registada com a chave do funcionário de piso.');
            return true;
        } catch (\RuntimeException $exception) {
            $this->sendError($exception->getMessage());
            return false;
        }
    }

    public function submitCorrect(): void
    {
        $data = $this->correctForm->getState();
        $holder = $this->parseHolderSelection($data['holder'] ?? null);

        if ($holder === null) {
            $this->sendError('Selecione um utilizador válido.');

            return;
        }

        if ($this->mode === 'floorCorrect') {
            if ($holder['type'] !== Teacher::class || $this->selectedFloorAccess === null) {
                $this->sendError('Selecione um professor válido para corrigir a abertura.');

                return;
            }

            $access = $this->selectedFloorAccess;
            DB::transaction(function () use ($access, $data, $holder): void {
                $before = KeyControlEvent::snapshotFloorKeyAccess($access);
                $access->forceFill([
                    'room_id' => (int) $data['room_id'],
                    'occupant_type' => Teacher::class,
                    'occupant_id' => $holder['id'],
                    'reason' => $data['reason'],
                ])->save();

                KeyControlEvent::log(
                    $access->keyControl?->originalEventKeyControlId() ?? $access->key_control_id,
                    KeyControlEvent::CORRECTED,
                    auth()->id(),
                    ['reason' => $data['reason'], 'before' => $before, 'after' => KeyControlEvent::snapshotFloorKeyAccess($access)],
                    $access->getKey()
                );
            });

            $this->sendSuccess('Abertura corrigida com sucesso.');
            $this->cancel();

            return;
        }

        $data['holder_type'] = $holder['type'];
        $data['holder_id'] = $holder['id'];

        $this->correctLatest((int) $this->selectedRoomId, $data);

        $this->cancel();
    }

    public function pickUp(int $roomId, string $holderType, int $holderId, ?string $observations): void
    {
        /** @var User $user */
        $user = auth()->user();

        $room = $this->getAuthorizedRoom($roomId);

        if ($room === null) {
            $this->sendError('A sala não está autorizada ou não existe.');

            return;
        }

        if (! in_array($holderType, [Teacher::class, Student::class], true)) {
            $this->sendError('O utilizador selecionado é inválido.');

            return;
        }

        $holder = $holderType::find($holderId);

        if ($holder === null) {
            $this->sendError('O utilizador selecionado é inválido.');

            return;
        }

        try {
            DB::transaction(function () use ($room, $holderType, $holderId, $holder, $observations, $user): void {
                Room::query()->lockForUpdate()->findOrFail($room->getKey());

                $exists = KeyControl::query()
                    ->where('room_id', $room->getKey())
                    ->whereNull('returned_at')
                    ->whereNull('room_released_at')
                    ->where('is_corrected', false)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw new \RuntimeException('Esta sala já tem uma chave levantada.');
                }

                $holderActiveKey = KeyControl::query()
                    ->where('holder_type', $holderType)
                    ->where('holder_id', $holderId)
                    ->whereNull('returned_at')
                    ->where('is_corrected', false)
                    ->with('room')
                    ->lockForUpdate()
                    ->first();

                if ($holderActiveKey !== null) {
                    throw new \RuntimeException($holder->name.' já tem a chave da sala '.$holderActiveKey->room?->name.' em sua posse. Não é possível ter duas chaves.');
                }

                $keyControl = KeyControl::create([
                    'room_id' => $room->getKey(),
                    'holder_type' => $holderType,
                    'holder_id' => $holderId,
                    'picked_up_at' => now(),
                    'pick_up_observations' => $observations,
                    'picked_up_by' => $user->getKey(),
                ]);

                KeyControlEvent::log(
                    $keyControl->originalEventKeyControlId(),
                    KeyControlEvent::KEY_PICKED_UP,
                    $user->getKey(),
                    ['observations' => $observations],
                    null,
                    $keyControl->picked_up_at
                );
            });

            $this->sendSuccess('Chave da sala '.$room->name.' entregue a '.$holder->name.' com sucesso.');
        } catch (\RuntimeException $e) {
            $this->sendError($e->getMessage());
        }
    }

    public function returnKey(int $roomId, ?string $observations): void
    {
        /** @var User $user */
        $user = auth()->user();

        $room = $this->getAuthorizedRoom($roomId);

        if ($room === null) {
            $this->sendError('A sala não está autorizada ou não existe.');

            return;
        }

        $active = DB::transaction(function () use ($room, $observations, $user): ?KeyControl {
            Room::query()->lockForUpdate()->findOrFail($room->getKey());

            $active = KeyControl::query()
                ->where('room_id', $room->getKey())
                ->whereNull('returned_at')
                ->whereNull('room_released_at')
                ->where('is_corrected', false)
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                $active->update([
                    'returned_at' => now(),
                    'return_observations' => $observations,
                    'returned_by' => $user->getKey(),
                ]);

                KeyControlEvent::log(
                    $active->originalEventKeyControlId(),
                    KeyControlEvent::KEY_RETURNED,
                    $user->getKey(),
                    ['return_observations' => $observations],
                    null,
                    $active->returned_at
                );
            }

            return $active;
        });

        if ($active === null) {
            $this->sendError('Esta sala não tem uma chave levantada.');

            return;
        }

        $this->sendSuccess($active->holderDisplayName().' entregou a chave da sala '.$room->name.' com sucesso.');
    }

    public function correctLatest(int $roomId, array $data): void
    {
        /** @var User $user */
        $user = auth()->user();

        $room = $this->getAuthorizedRoom($roomId);

        if ($room === null) {
            $this->sendError('A sala não está autorizada ou não existe.');

            return;
        }

        $latest = KeyControl::query()
            ->where('room_id', $room->getKey())
            ->where('is_corrected', false)
            ->latest('created_at')
            ->first();

        if ($latest === null) {
            $this->sendError('Não existe um registo disponível para corrigir.');

            return;
        }

        if (! $user->can('update', $latest)) {
            $this->sendError('Só pode corrigir o seu próprio movimento mais recente.');

            return;
        }

        $holderType = $data['holder_type'] ?? $latest->holder_type;
        $holderId = (int) ($data['holder_id'] ?? $latest->holder_id);

        if (! in_array($holderType, [Teacher::class, Student::class], true)
            || ! $holderType::query()->whereKey($holderId)->exists()) {
            $this->sendError('O utilizador selecionado é inválido.');

            return;
        }

        if (! $user->can('correct key control') && ! $this->isAuthorizedRoom($roomId)) {
            $this->sendError('A sala correta não está autorizada para si.');

            return;
        }

        try {
            \App\Filament\Resources\KeyControlResource::correctRecord($latest, $data['reason'], $data);
        } catch (\RuntimeException $e) {
            $this->sendError($e->getMessage());

            return;
        }

        $this->sendSuccess('Operação corrigida com sucesso.');
    }

    private function getAuthorizedRoom(int $roomId): ?Room
    {
        if (! $this->isAuthorizedRoom($roomId)) {
            return null;
        }

        return Room::find($roomId);
    }

    private function isAuthorizedRoom(int $roomId): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return UserBuildingAuthorization::query()
            ->where('user_id', $user->getKey())
            ->whereHas('building.rooms', fn ($query) => $query->whereKey($roomId))
            ->exists();
    }

    private function sendSuccess(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    private function sendError(string $message): void
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }

    public function getSelectedRoomNameProperty(): string
    {
        return $this->rooms->firstWhere('id', $this->selectedRoomId)?->name ?? 'Sala selecionada';
    }

    private function holderOptions(): array
    {
        return collect([Teacher::class, Student::class])
            ->flatMap(fn (string $holderType): array => $holderType::query()
                ->orderBy('name')
                ->get(['id', 'number', 'name'])
                ->mapWithKeys(fn ($holder): array => [
                    $holderType.':'.$holder->id => sprintf('%s - %s', $holder->number ?: 'Sem número', $holder->name),
                ])
                ->all())
            ->all();
    }

    private function parseHolderSelection(?string $selection): ?array
    {
        if (! is_string($selection) || ! str_contains($selection, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $selection, 2);

        if (! in_array($type, [Teacher::class, Student::class], true) || ! ctype_digit($id)) {
            return null;
        }

        if (! $type::query()->whereKey((int) $id)->exists()) {
            return null;
        }

        return ['type' => $type, 'id' => (int) $id];
    }
}
