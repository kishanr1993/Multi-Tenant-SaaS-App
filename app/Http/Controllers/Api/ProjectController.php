<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $user = $request->user();

        // Optimized query with eager loading to completely eliminate N+1 queries
        $query = Project::with(['manager', 'developers'])->withCount('tasks');

        // RBAC filtering
        if ($user->isProjectManager()) {
            $query->where(function ($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhereHas('developers', fn($devQuery) => $devQuery->where('users.id', $user->id));
            });
        } elseif ($user->isDeveloper()) {
            $query->whereHas('developers', fn($devQuery) => $devQuery->where('users.id', $user->id));
        }

        // Search by project name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortField = in_array($request->sort_by, ['id', 'name', 'created_at', 'status']) ? $request->sort_by : 'id';
        $sortOrder = strtolower($request->sort_order) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortOrder);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            ProjectResource::collection($paginator->items()),
            'Projects retrieved successfully.',
            Response::HTTP_OK,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $user = $request->user();

        $project = DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $developerIds = $data['developer_ids'] ?? [];
            unset($data['developer_ids']);

            if (!$user->isSuperAdmin() && empty($data['organization_id'])) {
                $data['organization_id'] = $user->organization_id;
            }

            if (empty($data['manager_id']) && $user->isProjectManager()) {
                $data['manager_id'] = $user->id;
            }

            $project = Project::create($data);

            if (!empty($developerIds)) {
                $project->developers()->sync($developerIds);
            }

            return $project;
        });

        $project->load(['manager', 'developers'])->loadCount('tasks');

        return $this->successResponse(
            new ProjectResource($project),
            'Project created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function show(int $id): JsonResponse
    {
        $project = Project::with(['manager', 'developers'])->withCount('tasks')->findOrFail($id);

        Gate::authorize('view', $project);

        return $this->successResponse(
            new ProjectResource($project),
            'Project details retrieved successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        Gate::authorize('delete', $project);

        $project->delete();

        return $this->successResponse(null, 'Project deleted successfully.');
    }
}
