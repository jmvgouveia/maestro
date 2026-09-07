<?php

namespace App\Policies;

use App\Models\EmailAudit;
use App\Models\User;

class EmailAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view-any EmailAudit');
    }

    public function view(User $user, EmailAudit $model): bool
    {
        return $user->checkPermissionTo('view EmailAudit');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EmailAudit $model): bool
    {
        return false;
    }

    public function delete(User $user, EmailAudit $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, EmailAudit $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, EmailAudit $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, EmailAudit $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
