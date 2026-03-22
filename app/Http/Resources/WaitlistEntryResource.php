<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lot_id' => $this->lot_id,
            'slot_id' => $this->slot_id,
            'priority' => $this->priority ?? 3,
            'status' => $this->status ?? 'waiting',
            'notified_at' => $this->notified_at,
            'offer_expires_at' => $this->offer_expires_at,
            'accepted_booking_id' => $this->accepted_booking_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lot' => ParkingLotResource::make($this->whenLoaded('lot')),
            'slot' => ParkingSlotResource::make($this->whenLoaded('slot')),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
