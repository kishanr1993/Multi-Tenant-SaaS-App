<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ORG_ADMIN = 'org_admin';
    case PROJECT_MANAGER = 'project_manager';
    case DEVELOPER = 'developer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ORG_ADMIN => 'Organization Admin',
            self::PROJECT_MANAGER => 'Project Manager',
            self::DEVELOPER => 'Developer',
        };
    }
}
