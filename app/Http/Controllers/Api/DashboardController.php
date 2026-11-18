<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Efficient Projects aggregation directly in MySQL (Zero N+1, Zero memory loading)
        $projectQuery = Project::query();
        $projectStats = $projectQuery->selectRaw("
            COUNT(*) as total_projects,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_projects,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_projects,
            COUNT(CASE WHEN status = 'on_hold' THEN 1 END) as on_hold_projects
        ")->first();

        // 2. Efficient Tasks aggregation directly in MySQL
        $taskQuery = Task::query();
        $taskStats = $taskQuery->selectRaw("
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN status IN ('open', 'in_progress', 'review') THEN 1 END) as pending_tasks,
            COUNT(CASE WHEN status = 'open' THEN 1 END) as open_tasks,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
            COUNT(CASE WHEN status = 'review' THEN 1 END) as review_tasks,
            COALESCE(SUM(estimated_hours), 0) as total_estimated_hours,
            COALESCE(SUM(actual_hours), 0) as total_actual_hours
        ")->first();

        // 3. Developer-wise Task Distribution
        // Aggregated via GROUP BY in the database
        $developerQuery = User::where('users.role', Role::DEVELOPER->value);
        if (!$user->isSuperAdmin()) {
            $developerQuery->where('users.organization_id', $user->organization_id);
        }

        $developerDistribution = $developerQuery
            ->leftJoin('tasks', function ($join) use ($user) {
                $join->on('tasks.assigned_to', '=', 'users.id');
                if (!$user->isSuperAdmin()) {
                    $join->where('tasks.organization_id', '=', $user->organization_id);
                }
            })
            ->selectRaw("
                users.id as developer_id,
                users.name as developer_name,
                COUNT(tasks.id) as total_assigned_tasks,
                COUNT(CASE WHEN tasks.status = 'completed' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN tasks.status IN ('open', 'in_progress', 'review') THEN 1 END) as pending_tasks,
                COALESCE(SUM(tasks.estimated_hours), 0) as total_estimated_hours,
                COALESCE(SUM(tasks.actual_hours), 0) as total_actual_hours
            ")
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_assigned_tasks')
            ->get();

        return $this->successResponse([
            'projects' => [
                'total' => (int) ($projectStats->total_projects ?? 0),
                'active' => (int) ($projectStats->active_projects ?? 0),
                'draft' => (int) ($projectStats->draft_projects ?? 0),
                'completed' => (int) ($projectStats->completed_projects ?? 0),
                'on_hold' => (int) ($projectStats->on_hold_projects ?? 0),
            ],
            'tasks' => [
                'total' => (int) ($taskStats->total_tasks ?? 0),
                'completed' => (int) ($taskStats->completed_tasks ?? 0),
                'pending' => (int) ($taskStats->pending_tasks ?? 0),
                'open' => (int) ($taskStats->open_tasks ?? 0),
                'in_progress' => (int) ($taskStats->in_progress_tasks ?? 0),
                'review' => (int) ($taskStats->review_tasks ?? 0),
                'total_estimated_hours' => (float) ($taskStats->total_estimated_hours ?? 0),
                'total_actual_hours' => (float) ($taskStats->total_actual_hours ?? 0),
            ],
            'developer_distribution' => $developerDistribution->map(fn($item) => [
                'developer_id' => $item->developer_id,
                'developer_name' => $item->developer_name,
                'total_assigned_tasks' => (int) $item->total_assigned_tasks,
                'completed_tasks' => (int) $item->completed_tasks,
                'pending_tasks' => (int) $item->pending_tasks,
                'total_estimated_hours' => (float) $item->total_estimated_hours,
                'total_actual_hours' => (float) $item->total_actual_hours,
            ]),
        ], 'Dashboard statistics retrieved successfully.');
    }
}
