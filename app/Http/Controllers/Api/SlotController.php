<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingSlot;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    public function store(Request $request, string $lotId)
    {
        $this->requireAdmin($request);
        $request->validate(['slot_number' => 'required|string']);
        $slot = ParkingSlot::create(array_merge(
            $request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']),
            ['lot_id' => $lotId]
        ));

        return response()->json($slot, 201);
    }

    public function update(Request $request, string $lotId, string $slotId)
    {
        $this->requireAdmin($request);
        $slot = ParkingSlot::where('lot_id', $lotId)->findOrFail($slotId);
        $slot->update($request->only(['slot_number', 'status', 'reserved_for_department', 'zone_id']));

        return response()->json($slot);
    }

    public function destroy(Request $request, string $lotId, string $slotId)
    {
        $this->requireAdmin($request);
        ParkingSlot::where('lot_id', $lotId)->findOrFail($slotId)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
