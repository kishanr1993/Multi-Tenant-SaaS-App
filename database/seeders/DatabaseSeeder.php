<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');

        // 1. Create Organizations
        $acme = Organization::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'status' => 'active',
        ]);

        $globex = Organization::create([
            'name' => 'Globex Industries',
            'slug' => 'globex-ind',
            'status' => 'active',
        ]);

        // 2. Create Users
        // Global Super Admin (No tenant bound)
        $superAdmin = User::create([
            'organization_id' => null,
            'name' => 'Super Administrator',
            'email' => 'superadmin@saas.test',
            'password' => $defaultPassword,
            'role' => Role::SUPER_ADMIN,
        ]);

        // Acme Corporation Users
        $acmeAdmin = User::create([
            'organization_id' => $acme->id,
            'name' => 'Alice Admin',
            'email' => 'admin@acme.test',
            'password' => $defaultPassword,
            'role' => Role::ORG_ADMIN,
        ]);

        $acmePm = User::create([
            'organization_id' => $acme->id,
            'name' => 'Peter Manager',
            'email' => 'pm@acme.test',
            'password' => $defaultPassword,
            'role' => Role::PROJECT_MANAGER,
        ]);

        $acmeDev1 = User::create([
            'organization_id' => $acme->id,
            'name' => 'Dave Developer',
            'email' => 'dev1@acme.test',
            'password' => $defaultPassword,
            'role' => Role::DEVELOPER,
        ]);

        $acmeDev2 = User::create([
            'organization_id' => $acme->id,
            'name' => 'Diana Developer',
            'email' => 'dev2@acme.test',
            'password' => $defaultPassword,
            'role' => Role::DEVELOPER,
        ]);

        // Globex Industries Users
        $globexAdmin = User::create([
            'organization_id' => $globex->id,
            'name' => 'George Admin',
            'email' => 'admin@globex.test',
            'password' => $defaultPassword,
            'role' => Role::ORG_ADMIN,
        ]);

        $globexPm = User::create([
            'organization_id' => $globex->id,
            'name' => 'Gary Manager',
            'email' => 'pm@globex.test',
            'password' => $defaultPassword,
            'role' => Role::PROJECT_MANAGER,
        ]);

        $globexDev = User::create([
            'organization_id' => $globex->id,
            'name' => 'Grace Developer',
            'email' => 'dev@globex.test',
            'password' => $defaultPassword,
            'role' => Role::DEVELOPER,
        ]);

        // 3. Create Projects for Acme
        $project1 = Project::create([
            'organization_id' => $acme->id,
            'name' => 'Cloud ERP Migration',
            'description' => 'Migrate legacy ERP systems to AWS cloud architecture.',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(60),
            'manager_id' => $acmePm->id,
            'status' => ProjectStatus::ACTIVE,
        ]);
        $project1->developers()->attach([$acmeDev1->id, $acmeDev2->id]);

        $project2 = Project::create([
            'organization_id' => $acme->id,
            'name' => 'Mobile App Redesign',
            'description' => 'Modernize React Native cross-platform application.',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(40),
            'manager_id' => $acmePm->id,
            'status' => ProjectStatus::DRAFT,
        ]);
        $project2->developers()->attach([$acmeDev1->id]);

        $project3 = Project::create([
            'organization_id' => $acme->id,
            'name' => 'Security Audit 2026',
            'description' => 'Annual SOC2 and ISO 27001 compliance audit.',
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(5),
            'manager_id' => $acmePm->id,
            'status' => ProjectStatus::COMPLETED,
        ]);
        $project3->developers()->attach([$acmeDev2->id]);

        // Project for Globex
        $globexProject = Project::create([
            'organization_id' => $globex->id,
            'name' => 'Globex Logistics Hub',
            'description' => 'Automated supply chain and freight tracking.',
            'start_date' => now()->subDays(15),
            'end_date' => now()->addDays(75),
            'manager_id' => $globexPm->id,
            'status' => ProjectStatus::ACTIVE,
        ]);
        $globexProject->developers()->attach([$globexDev->id]);

        // 4. Create Tasks for Acme
        $tasksData = [
            [
                'organization_id' => $acme->id,
                'project_id' => $project1->id,
                'assigned_to' => $acmeDev1->id,
                'title' => 'Design Database Schema',
                'description' => 'Create normalized schema diagrams and index optimization plan.',
                'priority' => TaskPriority::HIGH,
                'status' => TaskStatus::COMPLETED,
                'due_date' => now()->subDays(10),
                'estimated_hours' => 16.00,
                'actual_hours' => 14.50,
            ],
            [
                'organization_id' => $acme->id,
                'project_id' => $project1->id,
                'assigned_to' => $acmeDev1->id,
                'title' => 'Implement Authentication & RBAC',
                'description' => 'Setup Laravel Sanctum token auth with policy gates.',
                'priority' => TaskPriority::CRITICAL,
                'status' => TaskStatus::IN_PROGRESS,
                'due_date' => now()->addDays(5),
                'estimated_hours' => 24.00,
                'actual_hours' => 12.00,
            ],
            [
                'organization_id' => $acme->id,
                'project_id' => $project1->id,
                'assigned_to' => $acmeDev2->id,
                'title' => 'Build CI/CD Automated Pipelines',
                'description' => 'GitHub Actions workflows for tests, linting, and deploy.',
                'priority' => TaskPriority::MEDIUM,
                'status' => TaskStatus::REVIEW,
                'due_date' => now()->addDays(8),
                'estimated_hours' => 20.00,
                'actual_hours' => 18.00,
            ],
            [
                'organization_id' => $acme->id,
                'project_id' => $project1->id,
                'assigned_to' => $acmeDev2->id,
                'title' => 'Frontend Dashboard Integration',
                'description' => 'Wire REST endpoints to Vue/React dashboard widgets.',
                'priority' => TaskPriority::HIGH,
                'status' => TaskStatus::OPEN,
                'due_date' => now()->addDays(14),
                'estimated_hours' => 30.00,
                'actual_hours' => 0.00,
            ],
            [
                'organization_id' => $acme->id,
                'project_id' => $project1->id,
                'assigned_to' => null,
                'title' => 'Automated Integration Testing Suite',
                'description' => 'Write end-to-end API test assertions.',
                'priority' => TaskPriority::MEDIUM,
                'status' => TaskStatus::OPEN,
                'due_date' => now()->addDays(20),
                'estimated_hours' => 15.00,
                'actual_hours' => 0.00,
            ],
        ];

        foreach ($tasksData as $data) {
            $task = Task::create($data);

            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $acmePm->id,
                'action' => 'created',
                'details' => "Task created by {$acmePm->name}",
            ]);

            if ($task->assigned_to) {
                TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => $acmePm->id,
                    'action' => 'assigned',
                    'field' => 'assigned_to',
                    'new_value' => (string) $task->assigned_to,
                    'details' => "Assigned to developer ID #{$task->assigned_to}",
                ]);
            }
        }

        // Globex Task
        $globexTask = Task::create([
            'organization_id' => $globex->id,
            'project_id' => $globexProject->id,
            'assigned_to' => $globexDev->id,
            'title' => 'Setup Warehouse Inventory Service',
            'description' => 'REST API for inventory tracking and alerts.',
            'priority' => TaskPriority::HIGH,
            'status' => TaskStatus::IN_PROGRESS,
            'due_date' => now()->addDays(10),
            'estimated_hours' => 40.00,
            'actual_hours' => 20.00,
        ]);

        TaskActivity::create([
            'task_id' => $globexTask->id,
            'user_id' => $globexPm->id,
            'action' => 'created',
            'details' => "Task created by {$globexPm->name}",
        ]);
    }
}
