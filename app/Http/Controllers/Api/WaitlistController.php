<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Setting;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $entries = WaitlistEntry::with('lot')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => WaitlistEntryResource::collection($entries)]);
    }

    public function store(Request $request)
    {
        // Enforce waitlist_enabled setting
        if (Setting::get('waitlist_enabled', 'true') !== 'true') {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'WAITLIST_DISABLED', 'message' => 'Waitlist is disabled.'],
            ], 403);
        }

        $request->validate([
            'lot_id' => 'required|uuid|exists:parking_lots,id',
        ]);

        $entry = WaitlistEntry::firstOrCreate([
            'user_id' => $request->user()->id,
            'lot_id' => $request->lot_id,
        ]);

        return response()->json(['success' => true, 'data' => WaitlistEntryResource::make($entry)], 201);
    }

    public function destroy(Request $request, string $id)
    {
        $entry = WaitlistEntry::where('user_id', $request->user()->id)->findOrFail($id);
        $entry->delete();

        return response()->json(['success' => true, 'data' => null]);
    }
}
