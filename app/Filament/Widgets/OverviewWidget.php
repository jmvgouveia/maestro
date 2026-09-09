<?php

namespace App\Filament\Widgets;

use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\TeacherHourCounter;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class OverviewWidget extends Widget
{
    protected static string $view = 'filament.widgets.overview-widget';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $pollingInterval = '5s';

    protected int|string|array $columnSpan = 'full';

    public function render(): View
    {
        $userId = Filament::auth()->id();
        $teacher = Teacher::where('id_user', $userId)->first();

        if (! $teacher) {
            return view(static::$view, ['resumo' => []]);
        }

        // Obter ano letivo ativo
        $anoLetivoAtivo = SchoolYear::where('active', true)->first();

        if (! $anoLetivoAtivo) {
            return view(static::$view, ['resumo' => []]);
        }

        // Marcações aprovadas no ano letivo ativo
        $schedules = Schedule::with('subject')
            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
            ->where('id_teacher', $teacher->id)
            ->where('id_schoolyear', $anoLetivoAtivo->id)
            ->get();

        // Contador de carga horária
        $counter = TeacherHourCounter::where('id_teacher', $teacher->id)
            ->where('id_schoolyear', $anoLetivoAtivo->id)
            ->first();

        // $letivaDisponivel = $counter?->teaching_load ?? 0;
        // $naoLetivaDisponivel = $counter?->non_teaching_load ?? 0;

        // Aulas
        $aulasLetivas = $schedules->filter(fn ($s) => strtolower($s->subject->type ?? '') === 'letiva')->count();
        $aulasNaoLetivas = $schedules->filter(fn ($s) => strtolower($s->subject->type ?? '') === 'nao letiva')->count();

        $cargos = $teacher->positions()
            ->wherePivot('id_schoolyear', $anoLetivoAtivo->id)
            ->get()
            ->map(function ($cargo) use ($teacher, $anoLetivoAtivo) {
                $description = $cargo->description ?? 'Cargo sem descrição';

                if (str_contains($cargo->name, 'Coordenador de Polo/')
                    && str_contains($cargo->name, 'Núcleo')) {
                    $buildings = $this->teacherCoordinatorBuildingNames($teacher, $anoLetivoAtivo->id);

                    if ($buildings !== '') {
                        $description .= " | Polo/Núcleo: {$buildings}";
                    }
                }

                return [
                    'nome' => $cargo->name,
                    'descricao' => $description,
                    'redução_letiva' => $cargo->reduction_l ?? 0,
                    'redução_naoletiva' => $cargo->reduction_nl ?? 0,
                ];
            })->toArray();

        $tempoReducoes = $teacher->timeReductions()
            ->where('id_schoolyear', $anoLetivoAtivo->id)
            ->get()
            ->map(function ($tempoReducoes) {
                return [
                    'nome' => $tempoReducoes->name,
                    'descricao' => $tempoReducoes->description ?? 'Cargo sem descrição',
                    'redução_letiva' => $tempoReducoes->value_l ?? 0,
                    'redução_naoletiva' => $tempoReducoes->value_nl ?? 0,
                ];
            })->toArray();

        $horasExtras = $counter?->numovertime ?? 0;

        // $totalHoraLetiva = ($counter?->teaching_load ?? 0) - array_sum(array_column($cargos, 'redução_letiva')) - array_sum(array_column($tempoReducoes, 'redução_letiva'));

        $resumo = [
            'letiva' => $aulasLetivas,
            'nao_letiva' => $aulasNaoLetivas,
            'disponivel_letiva' => max(0, $counter?->teaching_load ?? 0),
            'disponivel_naoletiva' => max(0, $counter?->non_teaching_load ?? 0),
            'cargos' => $cargos,
            'tempo_reducoes' => $tempoReducoes,
            'horas_extras' => $horasExtras,
        ];

        return view(static::$view, compact('resumo'));
    }

    private function teacherCoordinatorBuildingNames(Teacher $teacher, int $schoolYearId): string
    {
        return $teacher->coordinatorBuildings()
            ->wherePivot('id_schoolyear', $schoolYearId)
            ->orderBy('buildings.name')
            ->pluck('buildings.name')
            ->implode(', ');
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasRole('Professor');
    }
}
