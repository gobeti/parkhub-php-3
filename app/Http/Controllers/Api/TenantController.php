<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'branding' => 'nullable|array',
            'branding.primary_color' => 'nullable|string|max:7',
            'branding.logo_url' => 'nullable|string|max:500',
            'branding.company_name' => 'nullable|string|max:255',
        ]);

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
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'branding' => 'nullable|array',
            'branding.primary_color' => 'nullable|string|max:7',
            'branding.logo_url' => 'nullable|string|max:500',
            'branding.company_name' => 'nullable|string|max:255',
        ]);

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
