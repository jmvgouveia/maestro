<?php

namespace App\Filament\Widgets;

use App\Models\SchoolYear;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class ScheduleWindowsWidget extends Widget
{
    protected static string $view = 'filament.widgets.schedule-windows-widget';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function render(): View
    {
        return view(static::$view, [
            'schoolYear' => SchoolYear::query()->where('active', true)->first(),
            'periods' => $this->periods(),
            'isStudent' => Filament::auth()->user()?->hasRole('Aluno'),
        ]);
    }

    public function periods(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! ($schoolYear = SchoolYear::query()->where('active', true)->first())) {
            return [];
        }

        if ($user->hasRole('Aluno')) {
            return [$this->period('Marcação dos alunos', $schoolYear->start_date_registration, $schoolYear->end_date_registration)];
        }

        return [
            $this->period('Especializado', $schoolYear->start_date_especializado, $schoolYear->end_date_especializado),
            $this->period('Profissional', $schoolYear->start_date_profissional, $schoolYear->end_date_profissional),
            $this->period('Livre', $schoolYear->start_date_livre, $schoolYear->end_date_livre),
        ];
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasAnyRole(['Professor', 'Aluno']);
    }

    private function period(string $name, ?string $start, ?string $end): array
    {
        if (! $start || ! $end) {
            return [
                'name' => $name,
                'dates' => 'Datas não configuradas',
                'status' => 'Não configurado',
                'statusColor' => 'gray',
                'message' => 'Configure este período no ano letivo.',
            ];
        }

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();
        $today = now();

        if ($today->lt($startDate)) {
            $days = $today->startOfDay()->diffInDays($startDate);

            return [
                'name' => $name,
                'dates' => $this->formatDates($startDate, $endDate),
                'status' => 'Ainda não começou',
                'statusColor' => 'gray',
                'message' => $this->relativeMessage($days, 'Começa'),
            ];
        }

        if ($today->gt($endDate)) {
            return [
                'name' => $name,
                'dates' => $this->formatDates($startDate, $endDate),
                'status' => 'Encerrado',
                'statusColor' => 'danger',
                'message' => 'Este período já terminou.',
            ];
        }

        $days = $today->startOfDay()->diffInDays($endDate->startOfDay());

        return [
            'name' => $name,
            'dates' => $this->formatDates($startDate, $endDate),
            'status' => 'Aberto',
            'statusColor' => 'success',
            'message' => $this->relativeMessage($days, 'Termina'),
        ];
    }

    private function formatDates(Carbon $start, Carbon $end): string
    {
        return $start->format('d/m/Y').' - '.$end->format('d/m/Y');
    }

    private function relativeMessage(int $days, string $verb): string
    {
        return $days === 0
            ? "{$verb} hoje"
            : "{$verb} em {$days} ".($days === 1 ? 'dia' : 'dias');
    }
}
