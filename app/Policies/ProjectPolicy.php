<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($project->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        if ($user->isProjectManager() && $project->manager_id === $user->id) {
            return true;
        }

        return $project->developers()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isOrgAdmin() || $user->isProjectManager();
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        if ($user->isProjectManager() && $project->manager_id === $user->id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->organization_id !== $user->organization_id) {
            return false;
        }

        return $user->isOrgAdmin();
    }

    public function assignDevelopers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
