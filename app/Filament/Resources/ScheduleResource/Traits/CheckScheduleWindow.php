<?php

namespace App\Filament\Resources\ScheduleResource\Traits;

use App\Models\Classes;
use App\Models\SchoolYear;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;

trait CheckScheduleWindow
{
    protected function validateScheduleWindow(?array $classIds = null, array $scheduleIds = []): void
    {
        if (Auth::user()?->isSuperAdmin()) {
            return;
        }

        $anoLetivo = SchoolYear::where('active', true)->first();

        if (! $anoLetivo) {
            Notification::make()
                ->title('Ano letivo não configurado')
                ->body('Não existe um ano letivo ativo para marcar horários.')
                ->warning()
                ->persistent()
                ->send();

            throw new Halt('Não existe um ano letivo ativo.');
        }

        $classIds ??= [];

        if ($scheduleIds !== []) {
            $classIds = array_merge(
                $classIds,
                Classes::query()
                    ->whereHas('schedules', fn ($query) => $query->whereIn('schedules.id', $scheduleIds))
                    ->pluck('id')
                    ->all(),
            );
        }

        $classes = Classes::query()
            ->with('course')
            ->whereIn('id', array_unique(array_filter($classIds)))
            ->get();

        $windows = [
            'Especializado' => [$anoLetivo->start_date_especializado, $anoLetivo->end_date_especializado],
            'Profissional' => [$anoLetivo->start_date_profissional, $anoLetivo->end_date_profissional],
            'Livre' => [$anoLetivo->start_date_livre, $anoLetivo->end_date_livre],
        ];

        $missingTypeCourses = $classes
            ->filter(fn (Classes $class) => blank($class->course?->type))
            ->map(fn (Classes $class) => $class->course?->name ?? "curso da turma {$class->name}")
            ->unique()
            ->values();

        if ($missingTypeCourses->isNotEmpty()) {
            $this->stopScheduleWindowValidation(
                'Cursos sem tipologia',
                'Configure a tipologia dos cursos: '.$missingTypeCourses->implode(', ').'.',
            );
        }

        $today = now()->toDateString();
        $typesToCheck = $classes->isEmpty()
            ? collect(array_keys($windows))
            : $classes->pluck('course.type')->unique();

        $closedTypes = $typesToCheck
            ->filter(function (string $type) use ($windows, $today): bool {
                [$start, $end] = $windows[$type] ?? [null, null];

                return ! $start || ! $end || $today < $start || $today > $end;
            })
            ->values();

        if ($closedTypes->isNotEmpty()) {
            $details = $closedTypes->map(function (string $type) use ($windows): string {
                [$start, $end] = $windows[$type] ?? [null, null];

                return $start && $end
                    ? "{$type} ({$this->formatDate($start)} a {$this->formatDate($end)})"
                    : "{$type} (datas não configuradas)";
            })->implode('; ');

            $this->stopScheduleWindowValidation(
                'Fora das balizas de marcação',
                "As seguintes balizas estão fechadas: {$details}.",
            );
        }
    }

    private function stopScheduleWindowValidation(string $title, string $body): never
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->persistent()
            ->send();

        throw new Halt($body);
    }

    private function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }
}
