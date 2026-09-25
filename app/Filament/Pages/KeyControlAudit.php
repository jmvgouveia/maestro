<?php

namespace App\Filament\Pages;

use App\Models\KeyControlEvent;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KeyControlAudit extends Page
{
    protected static string $view = 'filament.pages.key-control-audit';
    protected static ?string $slug = 'historico-chaves';
    protected static ?string $navigationGroup = 'Porteiro';
    protected static ?string $navigationLabel = 'Histórico';
    protected static ?string $title = 'Histórico de movimentos de chaves';
    protected static ?int $navigationSort = 3;

    public ?string $dateFrom = null;
    public ?string $dateUntil = null;
    public ?string $eventType = null;
    public ?int $roomId = null;
    public ?string $person = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view key control history') ?? false;
    }

    public function getRoomsProperty(): Collection
    {
        $query = Room::query();

        if (! $this->canSeeAllRooms()) {
            $query->whereHas('building.userBuildingAuthorizations', fn (Builder $query) => $query->where('user_id', auth()->id()));
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    public function getPeopleProperty(): array
    {
        return collect([Teacher::class, Student::class])->flatMap(fn (string $type): array => $type::query()
            ->orderBy('name')->get(['id', 'name', 'number'])
            ->mapWithKeys(fn ($person): array => [$type.':'.$person->id => ($person->number ? $person->number.' - ' : '').$person->name])
            ->all())->all();
    }

    public function getGroupedEventsProperty(): Collection
    {
        return $this->filteredEvents()->groupBy(fn (KeyControlEvent $event): int => $event->keyControl?->room_id ?? 0)
            ->map(fn (Collection $events): Collection => $events->groupBy(fn (KeyControlEvent $event): string => $this->personKey($event)));
    }

    public function filteredEvents(): Collection
    {
        $query = KeyControlEvent::query()
            ->with(['keyControl.room.building', 'keyControl.holder', 'performedBy'])
            ->when($this->dateFrom, fn (Builder $query) => $query->whereDate('occurred_at', '>=', $this->dateFrom))
            ->when($this->dateUntil, fn (Builder $query) => $query->whereDate('occurred_at', '<=', $this->dateUntil))
            ->when($this->eventType, fn (Builder $query) => $query->where('event_type', $this->eventType))
            ->when($this->roomId, fn (Builder $query) => $query->whereHas('keyControl', fn (Builder $query) => $query->where('room_id', $this->roomId)))
            ->when($this->person, function (Builder $query): void {
                [$type, $id] = explode(':', $this->person, 2);
                $query->where(function (Builder $query) use ($type, $id): void {
                    $query->whereHas('keyControl', fn (Builder $query) => $query->where('holder_type', $type)->where('holder_id', $id));
                    if ($type === Teacher::class) {
                        $query->orWhereJsonContains('data->occupant_id', (int) $id);
                    }
                });
            });

        if (! $this->canSeeAllRooms()) {
            $query->whereHas('keyControl.room.building.userBuildingAuthorizations', fn (Builder $query) => $query->where('user_id', auth()->id()));
        }

        return $query->orderBy('occurred_at')->get();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = $this->dateUntil = $this->eventType = $this->person = null;
        $this->roomId = null;
    }

    public function exportCsv(): StreamedResponse
    {
        $events = $this->filteredEvents();

        return response()->streamDownload(function () use ($events): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Operação', 'Sala', 'Professor/aluno', 'Registado por']);
            foreach ($events as $event) {
                fputcsv($handle, [$event->occurred_at?->format('d/m/Y H:i'), self::eventLabel($event->event_type, $event->data), $event->keyControl?->room?->name, $this->personName($event), $event->performedBy?->name ?? 'Automático']);
            }
            fclose($handle);
        }, 'historico-movimentos-chaves.csv');
    }

    public static function eventOptions(): array
    {
        return [KeyControlEvent::KEY_PICKED_UP => 'Levantamento', KeyControlEvent::KEY_RETURNED => 'Devolução', KeyControlEvent::ROOM_RELEASED => 'Chave não devolvida', KeyControlEvent::FLOOR_KEY_OPENED => 'Abertura com chave de piso', KeyControlEvent::FLOOR_USE_ENDED => 'Fim de utilização', KeyControlEvent::CORRECTED => 'Correção'];
    }

    public static function eventLabel(string $event, ?array $data = null): string
    {
        if ($event === KeyControlEvent::ROOM_RELEASED && ($data['release_type'] ?? null) === \App\Models\KeyControl::RELEASE_TYPE_DAILY_CLOSURE) {
            return 'Fecho diário automático';
        }

        return self::eventOptions()[$event] ?? $event;
    }

    public static function eventColor(string $event): string
    {
        return match ($event) { KeyControlEvent::KEY_RETURNED, KeyControlEvent::FLOOR_USE_ENDED => 'success', KeyControlEvent::ROOM_RELEASED => 'danger', KeyControlEvent::CORRECTED => 'warning', default => 'info' };
    }

    private function canSeeAllRooms(): bool
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin Porteiro']) ?? false;
    }

    private function personKey(KeyControlEvent $event): string
    {
        $occupantType = $event->data['occupant_type'] ?? null;
        $occupantId = $event->data['occupant_id'] ?? null;

        return $occupantType && $occupantId
            ? $occupantType.':'.$occupantId
            : $event->keyControl?->holder_type.':'.$event->keyControl?->holder_id;
    }

    public function personName(KeyControlEvent $event): string
    {
        return $event->data['occupant_name'] ?? $event->keyControl?->holderDisplayName() ?? 'Desconhecido';
    }
}
