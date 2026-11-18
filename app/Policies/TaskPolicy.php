<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
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

    public function view(User $user, Task $task): bool
    {
        if ($task->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        if ($user->isProjectManager() && $task->project->manager_id === $user->id) {
            return true;
        }

        return $task->assigned_to === $user->id ||
            $task->project->developers()->where('users.id', $user->id)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        if ($project->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        return $user->isProjectManager() && $project->manager_id === $user->id;
    }

    public function update(User $user, Task $task): bool
    {
        if ($task->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        return $user->isProjectManager() && $task->project->manager_id === $user->id;
    }

    public function updateStatus(User $user, Task $task): bool
    {
        if ($task->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        if ($user->isProjectManager() && $task->project->manager_id === $user->id) {
            return true;
        }

        // Assigned developer can update status
        return $task->assigned_to === $user->id;
    }

    public function assign(User $user, Task $task): bool
    {
        if ($task->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        return $user->isProjectManager() && $task->project->manager_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($task->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        return $user->isProjectManager() && $task->project->manager_id === $user->id;
    }
}
