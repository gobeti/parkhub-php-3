<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * GET /api/v1/admin/tenants — list all tenants.
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::withCount(['users', 'parkingLots'])
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'domain' => $t->domain,
                'branding' => $t->branding,
                'created_at' => $t->created_at?->toISOString(),
                'updated_at' => $t->updated_at?->toISOString(),
                'user_count' => $t->users_count,
                'lot_count' => $t->parking_lots_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * POST /api/v1/admin/tenants — create a new tenant.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'branding' => $validated['branding'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'branding' => $tenant->branding,
                'created_at' => $tenant->created_at?->toISOString(),
                'updated_at' => $tenant->updated_at?->toISOString(),
                'user_count' => 0,
                'lot_count' => 0,
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/admin/tenants/{id} — update a tenant.
     */
    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validated();

        $tenant->update([
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'branding' => $validated['branding'] ?? null,
        ]);

        $tenant->loadCount(['users', 'parkingLots']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'branding' => $tenant->branding,
                'created_at' => $tenant->created_at?->toISOString(),
                'updated_at' => $tenant->updated_at?->toISOString(),
                'user_count' => $tenant->users_count,
                'lot_count' => $tenant->parking_lots_count,
            ],
        ]);
    }
}
