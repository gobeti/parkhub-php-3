<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    private function requireAdmin($request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    public function announcements(Request $request)
    {
        $this->requireAdmin($request);

        return response()->json(Announcement::orderBy('created_at', 'desc')->get());
    }

    public function createAnnouncement(Request $request)
    {
        $this->requireAdmin($request);

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'severity' => 'nullable|in:info,warning,error,success',
            'expires_at' => 'nullable|date',
        ]);
        $ann = Announcement::create(array_merge(
            $request->only(['title', 'message', 'severity', 'expires_at']),
            ['created_by' => $request->user()->id, 'active' => true]
        ));

        return response()->json($ann, 201);
    }

    public function updateAnnouncement(Request $request, string $id)
    {
        $this->requireAdmin($request);
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'severity' => 'sometimes|in:info,warning,error,success',
            'active' => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date',
        ]);
        $ann = Announcement::findOrFail($id);
        $ann->update($request->only(['title', 'message', 'severity', 'active', 'expires_at']));

        return response()->json($ann);
    }

    public function deleteAnnouncement(Request $request, string $id)
    {
        $this->requireAdmin($request);
        Announcement::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
