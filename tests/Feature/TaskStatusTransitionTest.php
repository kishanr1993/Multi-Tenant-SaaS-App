<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Admin User',
            'email' => 'admin@transition.test',
            'password' => bcrypt('password123'),
            'role' => Role::ORG_ADMIN,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->org->id,
            'name' => 'Status Test Project',
            'status' => ProjectStatus::ACTIVE,
        ]);
    }

    public function test_valid_status_transition_from_open_to_in_progress(): void
    {
        $task = Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'title' => 'Open Task',
            'status' => TaskStatus::OPEN,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson('/api/tasks/' . $task->id . '/status', [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('in_progress', $task->fresh()->status->value);
    }

    public function test_invalid_status_transition_from_completed_to_open_is_rejected(): void
    {
        $task = Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'title' => 'Completed Task',
            'status' => TaskStatus::COMPLETED,
        ]);

        Sanctum::actingAs($this->admin);

        // Transitioning directly from completed to open violates transition rules
        $response = $this->patchJson('/api/tasks/' . $task->id . '/status', [
            'status' => 'open',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Assert database was NOT changed
        $this->assertEquals(TaskStatus::COMPLETED, $task->fresh()->status);
    }

    public function test_valid_transition_from_completed_to_review(): void
    {
        $task = Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'title' => 'Completed Task',
            'status' => TaskStatus::COMPLETED,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson('/api/tasks/' . $task->id . '/status', [
            'status' => 'review',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(TaskStatus::REVIEW, $task->fresh()->status);
    }

    public function test_status_transition_creates_task_activity_log(): void
    {
        $task = Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'title' => 'Tracked Task',
            'status' => TaskStatus::OPEN,
        ]);

        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/tasks/' . $task->id . '/status', [
            'status' => 'in_progress',
        ]);

        $activity = TaskActivity::where('task_id', $task->id)
            ->where('action', 'status_changed')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('status', $activity->field);
        $this->assertEquals('open', $activity->old_value);
        $this->assertEquals('in_progress', $activity->new_value);
        $this->assertEquals($this->admin->id, $activity->user_id);
    }
}
