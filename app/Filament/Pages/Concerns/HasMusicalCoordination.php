<?php

namespace App\Filament\Pages\Concerns;

use App\Models\RegistrationSubject;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HasMusicalCoordination
{
    public ?int $selectedMusicalGroup = null;

    abstract protected static function musicalSubjectName(): string;

    abstract protected static function musicalPermission(): string;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->checkPermissionTo(static::musicalPermission());
    }

    public function getRowsProperty(): array
    {
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');

        if (! $schoolYearId || ! static::canAccess()) {
            return [];
        }

        $subjectId = Subject::query()
            ->where('name', static::musicalSubjectName())
            ->value('id');

        if (! $subjectId) {
            return [];
        }

        $groups = [];

        Schedule::query()
            ->where('id_subject', $subjectId)
            ->where('id_schoolyear', $schoolYearId)
            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
            ->whereNotNull('shift')
            ->where('shift', '!=', '')
            ->with(['teacher', 'subject', 'classes', 'weekday', 'timeperiod', 'room'])
            ->orderBy('shift')
            ->orderBy('id')
            ->get()
            ->each(function (Schedule $schedule) use (&$groups): void {
                foreach ($schedule->classes as $class) {
                    $key = implode('|', [
                        $schedule->id_subject,
                        $schedule->id_teacher,
                        $class->id,
                        $schedule->shift,
                    ]);

                    $groups[$key] ??= [
                        'subject' => $schedule->subject?->name ?? static::musicalSubjectName(),
                        'teacher' => $schedule->teacher?->name ?? '—',
                        'class_id' => $class->id,
                        'class' => $class->name,
                        'shift' => $schedule->shift,
                        'limits' => [],
                        'schedule_ids' => [],
                        'slots' => [],
                    ];

                    $groups[$key]['schedule_ids'][] = $schedule->id;
                    if ($schedule->shift_limit !== null) {
                        $groups[$key]['limits'][] = (int) $schedule->shift_limit;
                    }

                    $groups[$key]['slots'][$schedule->id] = [
                        'day' => $schedule->weekday?->weekday ?? '—',
                        'time' => $schedule->timeperiod?->description ?? '—',
                        'room' => $schedule->room?->name ?? '—',
                    ];
                }
            });

        return collect($groups)
            ->values()
            ->map(function (array $group) use ($schoolYearId, $subjectId): array {
                $group['schedule_ids'] = array_values(array_unique($group['schedule_ids']));
                $group['limit'] = empty($group['limits']) ? null : min($group['limits']);
                unset($group['limits']);
                $group['slots'] = array_values($group['slots']);
                $group['students'] = $this->studentsForGroup($group, $schoolYearId, $subjectId);
                $group['enrolled'] = count($group['students']);
                $group['available'] = $group['limit'] === null
                    ? null
                    : max(0, $group['limit'] - $group['enrolled']);

                return $group;
            })
            ->all();
    }

    public function showMusicalStudents(int $group): void
    {
        $this->selectedMusicalGroup = $group;
    }

    public function closeMusicalStudents(): void
    {
        $this->selectedMusicalGroup = null;
    }

    public function exportMusicalGroups(): StreamedResponse
    {
        $rows = $this->rows;

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Disciplina', 'Professor', 'Turma', 'Turno', 'Limite', 'Inscritos', 'Vagas'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['subject'],
                    $row['teacher'],
                    $row['class'],
                    $row['shift'],
                    $row['limit'] ?? 'Sem limite',
                    $row['enrolled'],
                    $row['available'] ?? '—',
                ], ';');
            }

            fclose($handle);
        }, 'coordenacao-'.str(static::musicalSubjectName())->slug().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportMusicalGroup(int $group): StreamedResponse
    {
        $row = $this->rows[$group] ?? null;
        abort_unless($row !== null, 404);

        return response()->streamDownload(function () use ($row): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Número', 'Aluno'], ';');

            foreach ($row['students'] as $student) {
                fputcsv($handle, [$student['number'], $student['name']], ';');
            }

            fclose($handle);
        }, 'alunos-'.str($row['subject'].'-'.$row['class'].'-'.$row['shift'])->slug().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function studentsForGroup(array $group, int $schoolYearId, int $subjectId): array
    {
        return RegistrationSubject::query()
            ->where('id_subject', $subjectId)
            ->whereIn('id_schedule', $group['schedule_ids'])
            ->whereHas('registration', fn ($query) => $query
                ->where('id_schoolyear', $schoolYearId)
                ->where('id_class', $this->classIdForGroup($group)))
            ->with('registration.student')
            ->get()
            ->map(fn (RegistrationSubject $registrationSubject): ?array => $registrationSubject->registration?->student
                ? [
                    'number' => (string) $registrationSubject->registration->student->number,
                    'name' => $registrationSubject->registration->student->name,
                ]
                : null)
            ->filter()
            ->unique('number')
            ->sortBy('number')
            ->values()
            ->all();
    }

    protected function classIdForGroup(array $group): int
    {
        return (int) ($group['class_id'] ?? 0);
    }
}
