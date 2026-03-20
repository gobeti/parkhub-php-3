<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(string $lotId)
    {
        return ZoneResource::collection(Zone::where('lot_id', $lotId)->get());
    }

    public function store(Request $request, string $lotId)
    {
        $request->validate(['name' => 'required|string']);
        $zone = Zone::create(array_merge($request->only(['name', 'color', 'description']), ['lot_id' => $lotId]));

        return ZoneResource::make($zone)->response()->setStatusCode(201);
    }

    public function update(Request $request, string $lotId, string $id)
    {
        $zone = Zone::where('lot_id', $lotId)->findOrFail($id);
        $zone->update($request->only(['name', 'color', 'description']));

        return ZoneResource::make($zone);
    }

    public function destroy(string $lotId, string $id)
    {
        Zone::where('lot_id', $lotId)->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
