<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $pm;
    private User $dev1;
    private User $dev2;
    private Project $project;
    private Task $taskAssignedToDev1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Admin User',
            'email' => 'admin@rbac.test',
            'password' => bcrypt('password123'),
            'role' => Role::ORG_ADMIN,
        ]);

        $this->pm = User::create([
            'organization_id' => $this->org->id,
            'name' => 'PM User',
            'email' => 'pm@rbac.test',
            'password' => bcrypt('password123'),
            'role' => Role::PROJECT_MANAGER,
        ]);

        $this->dev1 = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Dev One',
            'email' => 'dev1@rbac.test',
            'password' => bcrypt('password123'),
            'role' => Role::DEVELOPER,
        ]);

        $this->dev2 = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Dev Two',
            'email' => 'dev2@rbac.test',
            'password' => bcrypt('password123'),
            'role' => Role::DEVELOPER,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->org->id,
            'name' => 'RBAC Project',
            'manager_id' => $this->pm->id,
            'status' => ProjectStatus::ACTIVE,
        ]);

        $this->taskAssignedToDev1 = Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'assigned_to' => $this->dev1->id,
            'title' => 'Assigned Task',
            'status' => TaskStatus::OPEN,
        ]);
    }

    public function test_developer_cannot_create_projects(): void
    {
        Sanctum::actingAs($this->dev1);

        $response = $this->postJson('/api/projects', [
            'name' => 'Dev Project',
        ]);

        $response->assertStatus(403);
    }

    public function test_org_admin_can_create_projects(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/projects', [
            'name' => 'Admin Project',
        ]);

        $response->assertStatus(201);
    }

    public function test_developer_cannot_delete_projects(): void
    {
        Sanctum::actingAs($this->dev1);

        $response = $this->deleteJson('/api/projects/' . $this->project->id);

        $response->assertStatus(403);
    }

    public function test_project_manager_can_create_task_under_managed_project(): void
    {
        Sanctum::actingAs($this->pm);

        $response = $this->postJson('/api/projects/' . $this->project->id . '/tasks', [
            'title' => 'New Task by PM',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
    }

    public function test_developer_cannot_create_tasks(): void
    {
        Sanctum::actingAs($this->dev1);

        $response = $this->postJson('/api/projects/' . $this->project->id . '/tasks', [
            'title' => 'Task attempt by Dev',
        ]);

        $response->assertStatus(403);
    }

    public function test_developer_cannot_update_unassigned_task_status(): void
    {
        // dev2 is NOT assigned to taskAssignedToDev1
        Sanctum::actingAs($this->dev2);

        $response = $this->patchJson('/api/tasks/' . $this->taskAssignedToDev1->id . '/status', [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(403);
    }

    public function test_developer_can_update_status_of_assigned_task(): void
    {
        // dev1 IS assigned to taskAssignedToDev1
        Sanctum::actingAs($this->dev1);

        $response = $this->patchJson('/api/tasks/' . $this->taskAssignedToDev1->id . '/status', [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('in_progress', $response->json('data.status'));
    }
}
