<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserBuildingAuthorization;

class UserBuildingAuthorizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage user room authorizations');
    }

    public function view(User $user, UserBuildingAuthorization $authorization): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, UserBuildingAuthorization $authorization): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, UserBuildingAuthorization $authorization): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->checkPermissionTo('manage user room authorizations');
    }
}
