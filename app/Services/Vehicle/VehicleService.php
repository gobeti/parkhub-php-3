<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;

/**
 * Owns the vehicle create/update business logic extracted from
 * VehicleController (T-1742, pass 2).
 *
 * Pure extraction — the fillable field list and user_id binding match
 * the previous inline controller implementation. Controllers remain
 * responsible for HTTP shaping and route-model binding / ownership
 * enforcement (which is done via the per-user scoped findOrFail
 * lookup in the controller so the service never sees a vehicle it
 * shouldn't operate on).
 */
final class VehicleService
{
    /**
     * Editable fields accepted by both create and update. Any other
     * keys in the input array are ignored, matching the controller's
     * `only(...)` guard against mass-assignment of server-owned
     * columns like user_id, photo_url, flagged.
     */
    private const array EDITABLE_FIELDS = ['plate', 'make', 'model', 'color', 'is_default'];

    /**
     * Persist a new vehicle owned by the given user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Vehicle
    {
        return Vehicle::create(array_merge(
            $this->allowed($data),
            ['user_id' => $user->id],
        ));
    }

    /**
     * Apply editable field updates to an existing (already
     * ownership-scoped) vehicle.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update($this->allowed($data));

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function allowed(array $data): array
    {
        return array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
    }
}
