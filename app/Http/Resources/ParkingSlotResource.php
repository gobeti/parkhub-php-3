<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParkingSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParkingSlot
 */
class ParkingSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'slot_number' => $this->slot_number,
            'number' => $this->number,
            'status' => $this->status,
            'slot_type' => $this->slot_type,
            'features' => $this->features,
            'reserved_for_department' => $this->reserved_for_department,
            'zone_id' => $this->zone_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lot' => ParkingLotResource::make($this->whenLoaded('lot')),
            'zone' => ZoneResource::make($this->whenLoaded('zone')),
        ];
    }
}
