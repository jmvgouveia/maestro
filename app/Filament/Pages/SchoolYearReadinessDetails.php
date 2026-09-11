<?php

namespace App\Filament\Pages;

use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SchoolYearReadinessDetails extends Page
{
    protected static ?string $navigationGroup = 'Gestão Pedagógica';

    protected static ?string $navigationLabel = 'Pendências do ano letivo';

    protected static ?string $title = 'Pendências do ano letivo';

    protected static ?string $slug = 'pendencias-ano-letivo';

    protected static string $view = 'filament.pages.school-year-readiness-details';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->hasAnyRole(['Super Admin', 'Secretaria', 'Área Pedagógica']);
    }

    public function studentsWithoutRegistration(): Collection
    {
        $schoolYearId = $this->activeSchoolYearId();

        if (! $schoolYearId) {
            return collect();
        }

        return Student::query()
            ->whereDoesntHave('registrations', fn ($query) => $query->where('id_schoolyear', $schoolYearId))
            ->orderBy('name')
            ->get(['id', 'number', 'name', 'email']);
    }

    public function teachersWithoutSubject(): Collection
    {
        $schoolYearId = $this->activeSchoolYearId();

        if (! $schoolYearId) {
            return collect();
        }

        return Teacher::query()
            ->whereDoesntHave('subjects', fn ($query) => $query->where('teacher_subjects.id_schoolyear', $schoolYearId))
            ->with('department')
            ->orderBy('name')
            ->get(['id', 'name', 'acronym', 'id_department']);
    }

    public function activeSchoolYear(): ?SchoolYear
    {
        return SchoolYear::query()->where('active', true)->first();
    }

    private function activeSchoolYearId(): ?int
    {
        return SchoolYear::query()->where('active', true)->value('id');
    }
}
