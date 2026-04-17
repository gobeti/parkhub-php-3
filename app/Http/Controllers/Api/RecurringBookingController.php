<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecurringBookingRequest;
use App\Http\Requests\UpdateRecurringBookingRequest;
use App\Http\Resources\RecurringBookingResource;
use App\Models\RecurringBooking;
use Illuminate\Http\Request;

class RecurringBookingController extends Controller
{
    public function index(Request $request)
    {
        return RecurringBookingResource::collection(
            RecurringBooking::where('user_id', $request->user()->id)->get()
        );
    }

    public function store(StoreRecurringBookingRequest $request)
    {
        $recurring = RecurringBooking::create(array_merge(
            $request->only(['lot_id', 'slot_id', 'days_of_week', 'start_date', 'end_date', 'start_time', 'end_time', 'vehicle_plate']),
            ['user_id' => $request->user()->id, 'active' => true]
        ));

        return RecurringBookingResource::make($recurring)->response()->setStatusCode(201);
    }

    public function update(UpdateRecurringBookingRequest $request, string $id)
    {
        $recurring = RecurringBooking::where('user_id', $request->user()->id)->findOrFail($id);
        $recurring->update($request->only(['days_of_week', 'start_date', 'end_date', 'start_time', 'end_time', 'vehicle_plate', 'active']));

        return RecurringBookingResource::make($recurring);
    }

    public function destroy(Request $request, string $id)
    {
        RecurringBooking::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
