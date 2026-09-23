<?php

namespace App\Policies;

use App\Models\KeyControl;
use App\Models\User;

class KeyControlPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view-any key control');
    }

    public function view(User $user, KeyControl $keyControl): bool
    {
        return $user->checkPermissionTo('view key control');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('operate key control')
            || $user->checkPermissionTo('correct key control');
    }

    public function update(User $user, KeyControl $keyControl): bool
    {
        if ($keyControl->is_corrected
            || $keyControl->original_key_control_id !== null
            || $keyControl->returned_at !== null) {
            return false;
        }

        if (! $user->checkPermissionTo('operate key control')) {
            return false;
        }

        return $this->isUsersOwnLatestOperation($user, $keyControl);
    }

    public function delete(User $user, KeyControl $keyControl): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, KeyControl $keyControl): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, KeyControl $keyControl): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, KeyControl $keyControl): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function operate(User $user): bool
    {
        return $user->checkPermissionTo('operate key control');
    }

    public function correct(User $user, KeyControl $keyControl): bool
    {
        return $this->update($user, $keyControl);
    }

    private function isUsersOwnLatestOperation(User $user, KeyControl $keyControl): bool
    {
        if ($keyControl->picked_up_by !== $user->getKey() && $keyControl->returned_by !== $user->getKey()) {
            return false;
        }

        $latest = KeyControl::query()
            ->where('room_id', $keyControl->room_id)
            ->where('is_corrected', false)
            ->latest('created_at')
            ->first();

        return $latest !== null && $latest->getKey() === $keyControl->getKey();
    }
}
