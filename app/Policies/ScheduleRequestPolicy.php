<?php

namespace App\Policies;

use App\Models\ScheduleRequest;
use App\Models\User;

class ScheduleRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view-any ScheduleRequest');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ScheduleRequest $schedulerequest): bool
    {
        return $user->checkPermissionTo('view ScheduleRequest')
            && (! $user->isTeacher() || $this->belongsToTeacher($user, $schedulerequest));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->checkPermissionTo('create ScheduleRequest');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ScheduleRequest $schedulerequest): bool
    {
        return $user->checkPermissionTo('update ScheduleRequest')
            && (! $user->isTeacher() || $this->belongsToTeacher($user, $schedulerequest));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ScheduleRequest $schedulerequest): bool
    {
        if ($user->isTeacher()) {
            return in_array($schedulerequest->status, ['Pendente', 'Recusado', 'Aprovado DP'], true)
                && (int) $schedulerequest->id_teacher_requester === (int) $user->teacher?->getKey();
        }

        return $user->checkPermissionTo('delete ScheduleRequest');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->checkPermissionTo('delete-any ScheduleRequest');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ScheduleRequest $schedulerequest): bool
    {
        return $user->checkPermissionTo('restore ScheduleRequest');
    }

    /**
     * Determine whether the user can restore any models.
     */
    public function restoreAny(User $user): bool
    {
        return $user->checkPermissionTo('restore-any ScheduleRequest');
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, ScheduleRequest $schedulerequest): bool
    {
        return $user->checkPermissionTo('replicate ScheduleRequest');
    }

    /**
     * Determine whether the user can reorder the models.
     */
    public function reorder(User $user): bool
    {
        return $user->checkPermissionTo('reorder ScheduleRequest');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ScheduleRequest $schedulerequest): bool
    {
        return $user->checkPermissionTo('force-delete ScheduleRequest');
    }

    /**
     * Determine whether the user can permanently delete any models.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->checkPermissionTo('force-delete-any ScheduleRequest');
    }

    private function belongsToTeacher(User $user, ScheduleRequest $request): bool
    {
        $teacherId = $user->teacher?->getKey();

        return $teacherId !== null
            && ((int) $request->id_teacher_requester === (int) $teacherId
                || $request->scheduleConflict()->where('id_teacher', $teacherId)->exists());
    }
}
