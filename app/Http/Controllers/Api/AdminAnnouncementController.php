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
    public function announcements(Request $request)
    {
        $this->authorize('viewAny', Announcement::class);

        return response()->json(Announcement::orderBy('created_at', 'desc')->get());
    }

    public function createAnnouncement(CreateAnnouncementRequest $request)
    {
        $this->authorize('create', Announcement::class);
        $ann = Announcement::create(array_merge(
            $request->only(['title', 'message', 'severity', 'expires_at']),
            ['created_by' => $request->user()->id, 'active' => true]
        ));

        return response()->json($ann, 201);
    }

    public function updateAnnouncement(UpdateAnnouncementRequest $request, string $id)
    {
        $ann = Announcement::findOrFail($id);
        $this->authorize('update', $ann);
        $ann->update($request->only(['title', 'message', 'severity', 'active', 'expires_at']));

        return response()->json($ann);
    }

    public function deleteAnnouncement(Request $request, string $id)
    {
        $ann = Announcement::findOrFail($id);
        $this->authorize('delete', $ann);
        $ann->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
