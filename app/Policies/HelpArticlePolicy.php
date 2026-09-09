<?php

namespace App\Policies;

use App\Models\HelpArticle;
use App\Models\User;

class HelpArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view-any HelpArticle');
    }

    public function view(User $user, HelpArticle $helpArticle): bool
    {
        return $user->checkPermissionTo('view HelpArticle');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('create HelpArticle');
    }

    public function update(User $user, HelpArticle $helpArticle): bool
    {
        return $user->checkPermissionTo('update HelpArticle');
    }

    public function delete(User $user, HelpArticle $helpArticle): bool
    {
        return $user->checkPermissionTo('delete HelpArticle');
    }

    public function deleteAny(User $user): bool
    {
        return $user->checkPermissionTo('delete-any HelpArticle');
    }

    public function restore(User $user, HelpArticle $helpArticle): bool
    {
        return $user->checkPermissionTo('restore HelpArticle');
    }

    public function restoreAny(User $user): bool
    {
        return $user->checkPermissionTo('restore-any HelpArticle');
    }

    public function replicate(User $user, HelpArticle $helpArticle): bool
    {
        return $user->checkPermissionTo('replicate HelpArticle');
    }

    public function reorder(User $user): bool
    {
        return $user->checkPermissionTo('reorder HelpArticle');
    }

    public function forceDelete(User $user, HelpArticle $helpArticle): bool
    {
        return $user->checkPermissionTo('force-delete HelpArticle');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->checkPermissionTo('force-delete-any HelpArticle');
    }
}
