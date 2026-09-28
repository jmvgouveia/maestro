<?php

namespace App\Filament\Pages;

use App\Models\Room;
use App\Models\Student;
use App\Models\KeyControlEvent;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KeyControlOffenders extends Page
{
    protected static string $view = 'filament.pages.key-control-offenders';

    protected static ?string $slug = 'alunos-mais-ocorrencias';

    protected static ?string $navigationGroup = 'GESTÃO DE CHAVES';
    protected static ?string $navigationParentItem = 'Consulta';
    protected static ?string $navigationLabel = 'Alunos com mais ocorrências';
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $title = 'Alunos com mais ocorrências';
    protected static ?int $navigationSort = 3;

    public ?string $dateFrom = null;
    public ?string $dateUntil = null;
    public ?int $roomId = null;
    public string $search = '';
    public string $rankingOrder = 'occurrences';
    public int $perPage = 10;
    public int $detailPage = 1;

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

    public function getOffendersProperty(): Collection
    {
        return $this->offenderQuery()->get()->map(fn (object $row): object => $row);
    }

    public function getOccurrenceRowsProperty(): Collection
    {
        $elapsed = 'TIMESTAMPDIFF(MINUTE, key_control_events.occurred_at, COALESCE(key_controls.returned_at, NOW()))';

        return $this->occurrenceBaseQuery()
            ->select([
                'key_control_events.id',
                'key_control_events.occurred_at as alert_at',
                'students.id as student_id',
                'students.name',
                'students.number',
                'key_controls.picked_up_at',
                'key_controls.returned_at',
            ])
            ->selectRaw("{$elapsed} AS delay_minutes")
            ->orderByDesc('key_control_events.occurred_at')
            ->orderByDesc('key_control_events.id')
            ->get();
    }

    public function getVisibleOccurrenceRowsProperty(): Collection
    {
        return $this->occurrenceRows->forPage($this->detailPage, $this->perPage)->values();
    }

    public function getDetailPageCountProperty(): int
    {
        return max(1, (int) ceil($this->occurrenceRows->count() / $this->perPage));
    }

    public function getTotalIncidentsProperty(): int
    {
        return (int) $this->offenders->sum('occurrences_count');
    }

    public function getTotalDelayMinutesProperty(): int
    {
        return (int) $this->offenders->sum('total_delay_minutes');
    }

    public function resetFilters(): void
    {
        $this->dateFrom = null;
        $this->dateUntil = null;
        $this->roomId = null;
        $this->search = '';
        $this->detailPage = 1;
    }

    public function updatedPerPage(): void
    {
        $this->detailPage = 1;
    }

    public function updatedSearch(): void
    {
        $this->detailPage = 1;
    }

    public function updatedDateFrom(): void
    {
        $this->detailPage = 1;
    }

    public function updatedDateUntil(): void
    {
        $this->detailPage = 1;
    }

    public function updatedRoomId(): void
    {
        $this->detailPage = 1;
    }

    public function previousDetailPage(): void
    {
        $this->detailPage = max(1, $this->detailPage - 1);
    }

    public function nextDetailPage(): void
    {
        $this->detailPage = min($this->detailPageCount, $this->detailPage + 1);
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('export key control'), 403);

        $rows = $this->occurrenceRows;

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Aluno', 'Número', 'Recebeu a chave', 'Alerta', 'Entregou a chave', 'Atraso após alerta'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    self::safeCsvValue($row->name),
                    self::safeCsvValue($row->number),
                    $row->picked_up_at ? date('d/m/Y H:i', strtotime($row->picked_up_at)) : '',
                    $row->alert_at ? date('d/m/Y H:i', strtotime($row->alert_at)) : '',
                    $row->returned_at ? date('d/m/Y H:i', strtotime($row->returned_at)) : 'Pendente',
                    self::formatDelay((int) $row->delay_minutes),
                ], ';');
            }

            fclose($handle);
        }, 'alunos-com-mais-ocorrencias.csv');
    }

    private function offenderQuery(): Builder
    {
        $query = $this->occurrenceBaseQuery();
        $elapsed = 'TIMESTAMPDIFF(MINUTE, key_control_events.occurred_at, COALESCE(key_controls.returned_at, NOW()))';

        $query
            ->select([
                'students.id',
                'students.name',
                'students.number',
            ])
            ->selectRaw('COUNT(key_control_events.id) AS occurrences_count')
            ->selectRaw('COUNT(DISTINCT key_controls.room_id) AS rooms_count')
            ->selectRaw('SUM(key_controls.returned_at IS NULL) AS pending_occurrences')
            ->selectRaw("SUM({$elapsed}) AS total_delay_minutes")
            ->selectRaw("MAX({$elapsed}) AS max_delay_minutes")
            ->selectRaw('MAX(key_control_events.occurred_at) AS last_occurrence')
            ->groupBy('students.id', 'students.name', 'students.number');

        if ($this->rankingOrder === 'delay') {
            return $query
                ->orderByDesc('total_delay_minutes')
                ->orderByDesc('occurrences_count')
                ->orderBy('students.name');
        }

        return $query
            ->orderByDesc('occurrences_count')
            ->orderByDesc('total_delay_minutes')
            ->orderBy('students.name');
    }

    private function occurrenceBaseQuery(): Builder
    {
        return KeyControlEvent::query()
            ->join('key_controls', 'key_controls.id', '=', 'key_control_events.key_control_id')
            ->join('students', function ($join): void {
                $join->on('students.id', '=', 'key_controls.holder_id')
                    ->where('key_controls.holder_type', Student::class);
            })
            ->join('rooms', 'rooms.id', '=', 'key_controls.room_id')
            ->where('key_control_events.event_type', KeyControlEvent::STUDENT_KEY_ALERT)
            ->whereRaw('key_controls.is_corrected = 0')
            ->when($this->dateFrom, fn (Builder $query) => $query->whereDate('key_control_events.occurred_at', '>=', $this->dateFrom))
            ->when($this->dateUntil, fn (Builder $query) => $query->whereDate('key_control_events.occurred_at', '<=', $this->dateUntil))
            ->when($this->roomId, fn (Builder $query) => $query->where('key_controls.room_id', $this->roomId))
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('students.name', 'like', '%'.$this->search.'%')
                        ->orWhere('students.number', 'like', '%'.$this->search.'%');
                });
            })
            ->when(! $this->canSeeAllRooms(), function (Builder $query): void {
                $query->whereHas('keyControl.room.building.userBuildingAuthorizations', fn (Builder $query) => $query->where('user_id', auth()->id()));
            });
    }

    private static function formatDelay(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    private function canSeeAllRooms(): bool
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin Porteiro']) ?? false;
    }

    private static function safeCsvValue(?string $value): string
    {
        $value ??= '';

        return in_array(mb_substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
