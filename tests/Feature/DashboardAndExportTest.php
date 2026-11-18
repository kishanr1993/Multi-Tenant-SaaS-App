<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAndExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $dev1;
    private Project $project1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Admin User',
            'email' => 'admin@dash.test',
            'password' => bcrypt('password123'),
            'role' => Role::ORG_ADMIN,
        ]);

        $this->dev1 = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Dave Dev',
            'email' => 'dave@dash.test',
            'password' => bcrypt('password123'),
            'role' => Role::DEVELOPER,
        ]);

        $this->project1 = Project::create([
            'organization_id' => $this->org->id,
            'name' => 'Alpha Project',
            'status' => ProjectStatus::ACTIVE,
        ]);

        Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project1->id,
            'assigned_to' => $this->dev1->id,
            'title' => 'Task 1',
            'status' => TaskStatus::COMPLETED,
            'priority' => TaskPriority::HIGH,
            'estimated_hours' => 10,
            'actual_hours' => 8,
        ]);

        Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project1->id,
            'assigned_to' => $this->dev1->id,
            'title' => 'Task 2',
            'status' => TaskStatus::IN_PROGRESS,
            'priority' => TaskPriority::MEDIUM,
            'estimated_hours' => 15,
            'actual_hours' => 5,
        ]);
    }

    public function test_dashboard_summary_returns_aggregated_statistics_and_developer_distribution(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.projects.total', 1)
            ->assertJsonPath('data.projects.active', 1)
            ->assertJsonPath('data.tasks.total', 2)
            ->assertJsonPath('data.tasks.completed', 1)
            ->assertJsonPath('data.tasks.pending', 1)
            ->assertJsonPath('data.tasks.total_estimated_hours', 25)
            ->assertJsonPath('data.tasks.total_actual_hours', 13);

        $devDist = $response->json('data.developer_distribution');
        $this->assertNotEmpty($devDist);
        $this->assertEquals($this->dev1->id, $devDist[0]['developer_id']);
        $this->assertEquals(2, $devDist[0]['total_assigned_tasks']);
    }

    public function test_task_export_streams_csv_with_correct_headers_and_records(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->get('/api/tasks/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Task ID', $content);
        $this->assertStringContainsString('Project Name', $content);
        $this->assertStringContainsString('Task 1', $content);
        $this->assertStringContainsString('Task 2', $content);
        $this->assertStringContainsString('Alpha Project', $content);
    }
}
