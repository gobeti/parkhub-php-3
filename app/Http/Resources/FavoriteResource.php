<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'slot_id' => $this->slot_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'slot' => ParkingSlotResource::make($this->whenLoaded('slot')),
        ];
    }
}
