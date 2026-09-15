<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\User;
use Carbon\Carbon;

class SchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole('Aluno')
            && ($user->isTeacher() || $user->checkPermissionTo('view-any Schedule'));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        return ! $user->hasRole('Aluno')
            && $user->checkPermissionTo('view Schedule')
            && (! $user->isTeacher() || $this->belongsToTeacher($user, $schedule));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ! $user->hasRole('Aluno')
            && ($user->isTeacher()
                || $user->checkPermissionTo('create Schedule')
                || $user->checkPermissionTo('create_schedule'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        return ! $user->hasRole('Aluno')
            && $user->checkPermissionTo('update Schedule')
            && (! $user->isTeacher() || $this->belongsToTeacher($user, $schedule));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        if ($user->isTeacher()) {
            return $user->checkPermissionTo('delete Schedule')
                && $this->belongsToTeacher($user, $schedule)
                && $this->isScheduleWindowOpen($schedule);
        }

        return $this->canManageGlobally($user) && $user->checkPermissionTo('delete Schedule');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('delete-any Schedule');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Schedule $schedule): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('restore Schedule');
    }

    /**
     * Determine whether the user can restore any models.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('restore-any Schedule');
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, Schedule $schedule): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('replicate Schedule');
    }

    /**
     * Determine whether the user can reorder the models.
     */
    public function reorder(User $user): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('reorder Schedule');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Schedule $schedule): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('force-delete Schedule');
    }

    /**
     * Determine whether the user can permanently delete any models.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canManageGlobally($user) && $user->checkPermissionTo('force-delete-any Schedule');
    }

    public function export(User $user): bool
    {
        return false;
    }

    private function belongsToTeacher(User $user, Schedule $schedule): bool
    {
        $teacherId = $user->teacher?->getKey();

        return $teacherId !== null && (int) $schedule->id_teacher === (int) $teacherId;
    }

    private function canManageGlobally(User $user): bool
    {
        return ! $user->isTeacher() && ! $user->hasRole('Aluno');
    }

    private function isScheduleWindowOpen(Schedule $schedule): bool
    {
        $schoolYear = SchoolYear::query()
            ->whereKey($schedule->id_schoolyear)
            ->where('active', true)
            ->first();

        if (! $schoolYear) {
            return false;
        }

        $windows = [
            'Especializado' => [$schoolYear->start_date_especializado, $schoolYear->end_date_especializado],
            'Profissional' => [$schoolYear->start_date_profissional, $schoolYear->end_date_profissional],
            'Livre' => [$schoolYear->start_date_livre, $schoolYear->end_date_livre],
        ];

        $classTypes = $schedule->classes()
            ->with('course')
            ->get()
            ->map(fn ($class) => $class->course?->type);

        if ($classTypes->contains(null)) {
            return false;
        }

        $types = $classTypes->isEmpty()
            ? collect(array_keys($windows))
            : $classTypes->unique();

        $today = Carbon::today();

        return $types->every(function (string $type) use ($windows, $today): bool {
            [$start, $end] = $windows[$type] ?? [null, null];

            return $start && $end
                && $today->greaterThanOrEqualTo(Carbon::parse($start))
                && $today->lessThanOrEqualTo(Carbon::parse($end));
        });
    }
}
