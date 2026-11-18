<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AssignTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Jobs\SendTaskAssignedNotificationJob;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        Gate::authorize('view', $project);

        $query = Task::where('project_id', $project->id)
            ->with(['assignedDeveloper', 'activities.user']);

        // Search by task title
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by developer
        if ($request->filled('developer_id')) {
            $query->where('assigned_to', $request->developer_id);
        }

        // Sorting
        $sortField = in_array($request->sort_by, ['id', 'title', 'due_date', 'priority', 'status', 'created_at'])
            ? $request->sort_by
            : 'id';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortOrder);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            TaskResource::collection($paginator->items()),
            'Tasks retrieved successfully.',
            Response::HTTP_OK,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreTaskRequest $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        Gate::authorize('create', [Task::class, $project]);

        $user = $request->user();

        $task = DB::transaction(function () use ($request, $project, $user) {
            $data = $request->validated();
            $data['project_id'] = $project->id;
            $data['organization_id'] = $project->organization_id;

            $task = Task::create($data);

            // Maintain task activity history
            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => 'created',
                'details' => "Task created by {$user->name}",
            ]);

            // Dispatch notification if developer is pre-assigned on creation
            if (!empty($task->assigned_to)) {
                TaskActivity::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'action' => 'assigned',
                    'field' => 'assigned_to',
                    'new_value' => (string) $task->assigned_to,
                    'details' => "Assigned to developer ID #{$task->assigned_to}",
                ]);

                SendTaskAssignedNotificationJob::dispatch($task->id, $task->assigned_to, $user->id);
            }

            return $task;
        });

        $task->load(['project', 'assignedDeveloper', 'activities.user']);

        return $this->successResponse(
            new TaskResource($task),
            'Task created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function updateStatus(UpdateTaskStatusRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $task = DB::transaction(function () use ($request, $id, $user) {
            // Pessimistic lock to prevent concurrent status corruption
            $task = Task::lockForUpdate()->with('project')->findOrFail($id);

            Gate::authorize('updateStatus', $task);

            $oldStatus = $task->status instanceof TaskStatus ? $task->status->value : $task->status;
            $newStatus = $request->input('status');

            $task->status = $newStatus;
            $task->save();

            // Track task status changes in activity history
            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => 'status_changed',
                'field' => 'status',
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
                'details' => "Status changed from {$oldStatus} to {$newStatus}",
            ]);

            return $task;
        });

        $task->load(['project', 'assignedDeveloper', 'activities.user']);

        return $this->successResponse(
            new TaskResource($task),
            'Task status updated successfully.'
        );
    }

    public function assign(AssignTaskRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $developerId = (int) $request->input('developer_id');

        $result = DB::transaction(function () use ($id, $developerId, $user) {
            // Prevent duplicate task assignments during simultaneous requests using pessimistic lock
            $task = Task::lockForUpdate()->with('project')->findOrFail($id);

            Gate::authorize('assign', $task);

            // Deduplication: if already assigned to the same developer, avoid redundant updates and notifications
            if ((int) $task->assigned_to === $developerId) {
                return [
                    'task' => $task,
                    'is_duplicate' => true,
                ];
            }

            $oldAssignedTo = $task->assigned_to;
            $task->assigned_to = $developerId;
            $task->save();

            $developer = User::find($developerId);

            // Log activity history
            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => 'assigned',
                'field' => 'assigned_to',
                'old_value' => (string) $oldAssignedTo,
                'new_value' => (string) $developerId,
                'details' => "Assigned to {$developer->name}",
            ]);

            // Dispatch background queue job for notification
            SendTaskAssignedNotificationJob::dispatch($task->id, $developerId, $user->id);

            return [
                'task' => $task,
                'is_duplicate' => false,
            ];
        });

        $task = $result['task'];
        $task->load(['project', 'assignedDeveloper', 'activities.user']);

        $message = $result['is_duplicate']
            ? 'Developer already assigned to this task.'
            : 'Developer assigned successfully.';

        return $this->successResponse(
            new TaskResource($task),
            $message
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        // Optimized export query with eager loading
        $query = Task::with(['project', 'assignedDeveloper']);

        // Optional filters
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('developer_id')) {
            $query->where('assigned_to', $request->developer_id);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tasks-export-' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // CSV Header
            fputcsv($handle, [
                'Task ID',
                'Project ID',
                'Project Name',
                'Title',
                'Priority',
                'Status',
                'Assigned Developer',
                'Due Date',
                'Estimated Hours',
                'Actual Hours',
                'Created At',
            ]);

            // Chunked processing to handle bulk data efficiently with constant memory usage
            $query->chunk(200, function ($tasks) use ($handle) {
                foreach ($tasks as $task) {
                    fputcsv($handle, [
                        $task->id,
                        $task->project_id,
                        $task->project?->name ?? 'N/A',
                        $task->title,
                        $task->priority instanceof \BackedEnum ? $task->priority->value : $task->priority,
                        $task->status instanceof \BackedEnum ? $task->status->value : $task->status,
                        $task->assignedDeveloper?->name ?? 'Unassigned',
                        $task->due_date?->format('Y-m-d') ?? '',
                        $task->estimated_hours,
                        $task->actual_hours,
                        $task->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, Response::HTTP_OK, $headers);
    }
}
