<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SchoolYearReadinessDetails;
use App\Models\Classes;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timeperiod;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class SchoolYearReadinessWidget extends Widget
{
    protected static string $view = 'filament.widgets.school-year-readiness-widget';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function render(): View
    {
        return view(static::$view, ['checks' => $this->checks()]);
    }

    public function checks(): array
    {
        $schoolYear = SchoolYear::query()->where('active', true)->first();

        if (! $schoolYear) {
            return [$this->check('Ano letivo ativo', 'Não existe um ano letivo ativo.', 'Bloqueante')];
        }

        $missingWindows = collect([
            'start_date_especializado', 'end_date_especializado',
            'start_date_profissional', 'end_date_profissional',
            'start_date_livre', 'end_date_livre',
        ])->filter(fn (string $field): bool => blank($schoolYear->{$field}));

        $coursesWithoutType = Course::query()->whereNull('type')->orWhere('type', '')->count();
        $classesCount = Classes::query()->count();
        $classesWithoutBuilding = Classes::query()->whereDoesntHave('buildings')->count();
        $studentsWithoutRegistration = Student::query()
            ->whereDoesntHave('registrations', fn ($query) => $query->where('id_schoolyear', $schoolYear->id))
            ->count();
        $teachersWithoutSubject = Teacher::query()
            ->whereDoesntHave('subjects', fn ($query) => $query->where('teacher_subjects.id_schoolyear', $schoolYear->id))
            ->count();
        $studentsCount = Student::query()->count();
        $teachersCount = Teacher::query()->count();

        return [
            $this->check(
                'Ano letivo ativo',
                "Ano letivo {$schoolYear->schoolyear} selecionado.",
                'Concluído',
            ),
            $this->check(
                'Datas de marcação',
                $missingWindows->isEmpty()
                    ? 'As três janelas de marcação estão configuradas.'
                    : "Faltam {$missingWindows->count()} datas de marcação.",
                $missingWindows->isEmpty() ? 'Concluído' : 'Bloqueante',
            ),
            $this->check(
                'Tipologia dos cursos',
                $coursesWithoutType === 0
                    ? 'Todos os cursos têm tipologia.'
                    : "{$coursesWithoutType} curso(s) sem tipologia.",
                $coursesWithoutType === 0 ? 'Concluído' : 'Bloqueante',
            ),
            $this->check(
                'Períodos horários',
                Timeperiod::query()->where('active', true)->exists()
                    ? 'Existem períodos horários ativos.'
                    : 'Não existem períodos horários ativos.',
                Timeperiod::query()->where('active', true)->exists() ? 'Concluído' : 'Bloqueante',
            ),
            $this->check(
                'Edifícios das turmas',
                $classesCount === 0
                    ? 'Não existem turmas configuradas.'
                    : ($classesWithoutBuilding === 0
                    ? 'Todas as turmas têm edifícios permitidos.'
                    : "{$classesWithoutBuilding} turma(s) sem edifício permitido."),
                $classesCount > 0 && $classesWithoutBuilding === 0 ? 'Concluído' : 'Atenção',
            ),
            $this->check(
                'Disciplinas dos cursos',
                CourseSubject::query()->where('id_schoolyear', $schoolYear->id)->exists()
                    ? 'Existem disciplinas configuradas para o ano letivo.'
                    : 'Não existem disciplinas configuradas para o ano letivo.',
                CourseSubject::query()->where('id_schoolyear', $schoolYear->id)->exists()
                    ? 'Concluído'
                    : 'Atenção',
            ),
            $this->check(
                'Matrículas dos alunos',
                $studentsCount === 0
                    ? 'Não existem alunos registados.'
                    : ($studentsWithoutRegistration === 0
                        ? 'Todos os alunos têm matrícula no ano letivo.'
                        : "{$studentsWithoutRegistration} aluno(s) sem matrícula no ano letivo."),
                $studentsCount > 0 && $studentsWithoutRegistration === 0 ? 'Concluído' : 'Atenção',
                SchoolYearReadinessDetails::getUrl(),
            ),
            $this->check(
                'Disciplinas dos professores',
                $teachersCount === 0
                    ? 'Não existem professores registados.'
                    : ($teachersWithoutSubject === 0
                        ? 'Todos os professores têm disciplinas atribuídas.'
                        : "{$teachersWithoutSubject} professor(es) sem disciplinas atribuídas."),
                $teachersCount > 0 && $teachersWithoutSubject === 0 ? 'Concluído' : 'Atenção',
                SchoolYearReadinessDetails::getUrl(),
            ),
        ];
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->hasAnyRole(['Super Admin', 'Secretaria', 'Área Pedagógica']);
    }

    private function check(string $name, string $description, string $status, ?string $url = null): array
    {
        return compact('name', 'description', 'status', 'url');
    }
}
