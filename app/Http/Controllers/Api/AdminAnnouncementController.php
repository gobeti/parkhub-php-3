<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
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

    public function createAnnouncement(CreateAnnouncementRequest $request)
    {
        $ann = Announcement::create(array_merge(
            $request->only(['title', 'message', 'severity', 'expires_at']),
            ['created_by' => $request->user()->id, 'active' => true]
        ));

        return response()->json($ann, 201);
    }

    public function updateAnnouncement(UpdateAnnouncementRequest $request, string $id)
    {
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
