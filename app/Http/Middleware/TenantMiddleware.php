<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Task;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request and ensure strict tenant data isolation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Super Admin can access all or optionally switch context via X-Organization-ID header
        if ($user->role === Role::SUPER_ADMIN || $user->role === Role::SUPER_ADMIN->value) {
            return $next($request);
        }

        // Standard tenant user must belong to an organization
        if (!$user->organization_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any organization.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Route parameter isolation validation against ID manipulation attacks
        $route = $request->route();

        if ($route) {
            // Check Project parameter if present
            $projectParam = $route->parameter('project') ?? $route->parameter('id');
            if ($projectParam instanceof Project) {
                if ($projectParam->organization_id !== $user->organization_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found or unauthorized.'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            // Check Task parameter if present
            $taskParam = $route->parameter('task');
            if ($taskParam instanceof Task) {
                if ($taskParam->organization_id !== $user->organization_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found or unauthorized.'
                    ], Response::HTTP_NOT_FOUND);
                }
            }
        }

        return $next($request);
    }
}
