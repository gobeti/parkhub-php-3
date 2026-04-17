<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRbacRolesRequest;
use App\Http\Requests\StoreRbacRoleRequest;
use App\Http\Requests\UpdateRbacRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RBACController extends Controller
{
    /**
     * Default role definitions used when seeding / listing.
     */
    private const BUILT_IN_ROLES = [
        'super_admin' => [
            'description' => 'Full system access',
            'permissions' => ['manage_users', 'manage_lots', 'manage_bookings', 'view_reports', 'manage_settings', 'manage_plugins'],
        ],
        'admin' => [
            'description' => 'Administrative access',
            'permissions' => ['manage_users', 'manage_lots', 'manage_bookings', 'view_reports', 'manage_settings'],
        ],
        'manager' => [
            'description' => 'Lot and booking management',
            'permissions' => ['manage_lots', 'manage_bookings', 'view_reports'],
        ],
        'user' => [
            'description' => 'Standard user',
            'permissions' => ['view_reports'],
        ],
        'viewer' => [
            'description' => 'Read-only access',
            'permissions' => ['view_reports'],
        ],
    ];

    private const VALID_PERMISSIONS = [
        'manage_users',
        'manage_lots',
        'manage_bookings',
        'view_reports',
        'manage_settings',
        'manage_plugins',
    ];

    /**
     * GET /api/v1/admin/roles — list all roles.
     */
    public function listRoles(): JsonResponse
    {
        $this->ensureRolesTable();

        $roles = DB::table('rbac_roles')->orderBy('built_in', 'desc')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'permissions' => json_decode($r->permissions, true) ?: [],
                'built_in' => (bool) $r->built_in,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/admin/roles — create a custom role.
     */
    public function createRole(StoreRbacRoleRequest $request): JsonResponse
    {
        $this->ensureRolesTable();

        if (DB::table('rbac_roles')->where('name', $request->input('name'))->exists()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'DUPLICATE', 'message' => 'Role name already exists'],
            ], 409);
        }

        $id = Str::uuid()->toString();
        $now = now()->toISOString();

        DB::table('rbac_roles')->insert([
            'id' => $id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'permissions' => json_encode(array_values(array_unique($request->input('permissions')))),
            'built_in' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'permissions' => array_values(array_unique($request->input('permissions'))),
                'built_in' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/admin/roles/{id} — update a role.
     */
    public function updateRole(UpdateRbacRoleRequest $request, string $id): JsonResponse
    {
        $this->ensureRolesTable();

        $role = DB::table('rbac_roles')->where('id', $id)->first();
        if (! $role) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Role not found'],
            ], 404);
        }

        $updates = ['updated_at' => now()->toISOString()];

        if ($request->has('name')) {
            $dup = DB::table('rbac_roles')->where('name', $request->input('name'))->where('id', '!=', $id)->exists();
            if ($dup) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'DUPLICATE', 'message' => 'Role name already exists'],
                ], 409);
            }
            $updates['name'] = $request->input('name');
        }

        if ($request->has('description')) {
            $updates['description'] = $request->input('description');
        }

        if ($request->has('permissions')) {
            $updates['permissions'] = json_encode(array_values(array_unique($request->input('permissions'))));
        }

        DB::table('rbac_roles')->where('id', $id)->update($updates);

        $updated = DB::table('rbac_roles')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $updated->id,
                'name' => $updated->name,
                'description' => $updated->description,
                'permissions' => json_decode($updated->permissions, true) ?: [],
                'built_in' => (bool) $updated->built_in,
                'created_at' => $updated->created_at,
                'updated_at' => $updated->updated_at,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/permissions — list all available permissions.
     */
    public function listPermissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => array_map(fn ($p) => [
                'key' => $p,
                'label' => ucwords(str_replace('_', ' ', $p)),
            ], self::VALID_PERMISSIONS),
        ]);
    }

    /**
     * GET /api/v1/admin/users/{userId}/roles — get roles assigned to a user.
     */
    public function getUserRoles(string $userId): JsonResponse
    {
        $this->ensureRolesTable();

        $roleIds = DB::table('rbac_user_roles')->where('user_id', $userId)->pluck('role_id');
        $roles = DB::table('rbac_roles')->whereIn('id', $roleIds)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'roles' => $roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'permissions' => json_decode($r->permissions, true) ?: [],
                ])->values(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/admin/users/{userId}/roles — assign roles to a user.
     */
    public function assignRoles(AssignRbacRolesRequest $request, string $userId): JsonResponse
    {
        $this->ensureRolesTable();

        $validRoleIds = DB::table('rbac_roles')->whereIn('id', $request->input('role_ids'))->pluck('id');

        DB::table('rbac_user_roles')->where('user_id', $userId)->delete();

        foreach ($validRoleIds as $roleId) {
            DB::table('rbac_user_roles')->insert([
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => now()->toISOString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'role_ids' => $validRoleIds->values(),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/admin/roles/{id} — delete a custom role.
     */
    public function deleteRole(string $id): JsonResponse
    {
        $this->ensureRolesTable();

        $role = DB::table('rbac_roles')->where('id', $id)->first();
        if (! $role) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Role not found'],
            ], 404);
        }

        if ($role->built_in) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot delete built-in roles'],
            ], 403);
        }

        DB::table('rbac_user_roles')->where('role_id', $id)->delete();
        DB::table('rbac_roles')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted']);
    }

    /**
     * Ensure rbac tables exist and seed built-in roles.
     */
    private function ensureRolesTable(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('rbac_roles')) {
            DB::getSchemaBuilder()->create('rbac_roles', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->json('permissions');
                $table->boolean('built_in')->default(false);
                $table->timestamps();
            });

            DB::getSchemaBuilder()->create('rbac_user_roles', function ($table) {
                $table->uuid('id')->primary();
                $table->string('user_id');
                $table->string('role_id');
                $table->timestamp('created_at')->nullable();
                $table->unique(['user_id', 'role_id']);
            });

            // Seed built-in roles
            foreach (self::BUILT_IN_ROLES as $name => $def) {
                DB::table('rbac_roles')->insert([
                    'id' => Str::uuid()->toString(),
                    'name' => $name,
                    'description' => $def['description'],
                    'permissions' => json_encode($def['permissions']),
                    'built_in' => true,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ]);
            }
        }
    }
}
