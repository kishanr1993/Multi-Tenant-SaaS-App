<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendTaskAssignedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $taskId,
        public int $developerId,
        public ?int $assignedById = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = Task::withoutGlobalScopes()->with('project')->find($this->taskId);
        $developer = User::find($this->developerId);

        if (!$task || !$developer) {
            Log::warning("Task or developer not found for notification job", [
                'task_id' => $this->taskId,
                'developer_id' => $this->developerId
            ]);
            return;
        }

        // Idempotency / Deduplication check:
        // Prevent duplicate notifications if one was already issued for this task assignment
        $alreadyNotified = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $developer->id)
            ->where('type', TaskAssignedNotification::class)
            ->whereJsonContains('data->task_id', $task->id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($alreadyNotified) {
            Log::info("Skipping duplicate notification for task {$task->id} to developer {$developer->id}");
            return;
        }

        $assignedBy = $this->assignedById ? User::find($this->assignedById) : null;
        $developer->notify(new TaskAssignedNotification($task, $assignedBy));

        Log::info("Sent task assignment notification for task {$task->id} to developer {$developer->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("Failed to send task assigned notification job: " . $exception?->getMessage(), [
            'task_id' => $this->taskId,
            'developer_id' => $this->developerId,
            'exception' => $exception
        ]);
    }
}
