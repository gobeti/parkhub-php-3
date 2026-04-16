<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'lot_id' => $this->lot_id,
            'slot_id' => $this->slot_id,
            'guest_name' => $this->guest_name,
            'guest_code' => $this->guest_code,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'vehicle_plate' => $this->vehicle_plate,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lot' => ParkingLotResource::make($this->whenLoaded('lot')),
            'slot' => ParkingSlotResource::make($this->whenLoaded('slot')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
        ];
    }
}
