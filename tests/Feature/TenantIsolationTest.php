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

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $adminA;
    private User $adminB;
    private User $superAdmin;
    private Project $projectA;
    private Project $projectB;
    private Task $taskA;
    private Task $taskB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b']);

        $this->superAdmin = User::create([
            'organization_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@saas.test',
            'password' => bcrypt('password123'),
            'role' => Role::SUPER_ADMIN,
        ]);

        $this->adminA = User::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Admin A',
            'email' => 'admin@orga.test',
            'password' => bcrypt('password123'),
            'role' => Role::ORG_ADMIN,
        ]);

        $this->adminB = User::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Admin B',
            'email' => 'admin@orgb.test',
            'password' => bcrypt('password123'),
            'role' => Role::ORG_ADMIN,
        ]);

        $this->projectA = Project::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Project Alpha',
            'status' => ProjectStatus::ACTIVE,
        ]);

        $this->projectB = Project::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Project Beta',
            'status' => ProjectStatus::ACTIVE,
        ]);

        $this->taskA = Task::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Task in Org A',
            'status' => TaskStatus::OPEN,
        ]);

        $this->taskB = Task::create([
            'organization_id' => $this->orgB->id,
            'project_id' => $this->projectB->id,
            'title' => 'Task in Org B',
            'status' => TaskStatus::OPEN,
        ]);
    }

    public function test_organization_a_user_cannot_see_organization_b_projects(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/projects');

        $response->assertStatus(200);
        $data = $response->json('data');

        $projectIds = collect($data)->pluck('id');
        $this->assertTrue($projectIds->contains($this->projectA->id));
        $this->assertFalse($projectIds->contains($this->projectB->id));
    }

    public function test_user_cannot_access_project_from_another_organization_via_id_manipulation(): void
    {
        Sanctum::actingAs($this->adminA);

        // Attempt to fetch project belonging to Org B by ID
        $response = $this->getJson('/api/projects/' . $this->projectB->id);

        $response->assertStatus(404);
    }

    public function test_user_cannot_fetch_tasks_of_another_organization_project(): void
    {
        Sanctum::actingAs($this->adminA);

        // Attempt to fetch tasks under Org B's project
        $response = $this->getJson('/api/projects/' . $this->projectB->id . '/tasks');

        $response->assertStatus(404);
    }

    public function test_super_admin_can_access_projects_across_all_organizations(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/projects');

        $response->assertStatus(200);
        $projectIds = collect($response->json('data'))->pluck('id');

        $this->assertTrue($projectIds->contains($this->projectA->id));
        $this->assertTrue($projectIds->contains($this->projectB->id));
    }

    public function test_creating_project_automatically_binds_to_authenticated_user_organization(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->postJson('/api/projects', [
            'name' => 'Newly Created Project',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->orgA->id, $response->json('data.organization_id'));
    }
}
