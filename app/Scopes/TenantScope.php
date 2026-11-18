<?php

namespace App\Scopes;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the tenant scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        // Super admins have global access across all organizations
        if ($user->role === Role::SUPER_ADMIN || $user->role === Role::SUPER_ADMIN->value) {
            return;
        }

        if ($user->organization_id) {
            $builder->where($model->getTable() . '.organization_id', $user->organization_id);
        } else {
            // User without organization cannot access tenant records
            $builder->whereRaw('1 = 0');
        }
    }
}
