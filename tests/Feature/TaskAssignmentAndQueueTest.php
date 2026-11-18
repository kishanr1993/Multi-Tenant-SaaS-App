<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Jobs\SendTaskAssignedNotificationJob;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAssignmentAndQueueTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $admin;
    private User $dev;
    private User $foreignDev;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Acme Corp', 'slug' => 'acme']);
        $this->otherOrg = Organization::create(['name' => 'Globex', 'slug' => 'globex']);

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Admin User',
            'email' => 'admin@assign.test',
            'password' => bcrypt('password123'),
            'role' => Role::ORG_ADMIN,
        ]);

        $this->dev = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Dev User',
            'email' => 'dev@assign.test',
            'password' => bcrypt('password123'),
            'role' => Role::DEVELOPER,
        ]);

        $this->foreignDev = User::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Foreign Dev',
            'email' => 'foreigndev@globex.test',
            'password' => bcrypt('password123'),
            'role' => Role::DEVELOPER,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->org->id,
            'name' => 'Assignment Project',
            'status' => ProjectStatus::ACTIVE,
        ]);

        $this->task = Task::create([
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'title' => 'Task for Assignment',
            'status' => TaskStatus::OPEN,
        ]);
    }

    public function test_assigning_developer_dispatches_notification_job(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/tasks/' . $this->task->id . '/assign', [
            'developer_id' => $this->dev->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->dev->id, $this->task->fresh()->assigned_to);

        Queue::assertPushed(SendTaskAssignedNotificationJob::class, function ($job) {
            return $job->taskId === $this->task->id && $job->developerId === $this->dev->id;
        });
    }

    public function test_job_creates_database_notification_for_developer(): void
    {
        // Execute the job directly
        $job = new SendTaskAssignedNotificationJob($this->task->id, $this->dev->id, $this->admin->id);
        $job->handle();

        $notification = DB::table('notifications')
            ->where('notifiable_id', $this->dev->id)
            ->where('type', TaskAssignedNotification::class)
            ->first();

        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertEquals($this->task->id, $data['task_id']);
        $this->assertEquals($this->admin->name, $data['assigned_by']);
    }

    public function test_repeated_assignment_does_not_create_duplicate_notifications(): void
    {
        // First run
        $job1 = new SendTaskAssignedNotificationJob($this->task->id, $this->dev->id, $this->admin->id);
        $job1->handle();

        $countBefore = DB::table('notifications')
            ->where('notifiable_id', $this->dev->id)
            ->count();
        $this->assertEquals(1, $countBefore);

        // Immediate retry / repeated assignment job
        $job2 = new SendTaskAssignedNotificationJob($this->task->id, $this->dev->id, $this->admin->id);
        $job2->handle();

        $countAfter = DB::table('notifications')
            ->where('notifiable_id', $this->dev->id)
            ->count();

        // Idempotency check prevents duplicate record
        $this->assertEquals(1, $countAfter);
    }

    public function test_cannot_assign_developer_from_another_organization(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/tasks/' . $this->task->id . '/assign', [
            'developer_id' => $this->foreignDev->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['developer_id']);
    }
}
