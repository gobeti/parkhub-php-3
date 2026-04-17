<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SwapRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SwapRequest
 */
class SwapRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester_booking_id' => $this->requester_booking_id,
            'target_booking_id' => $this->target_booking_id,
            'requester_id' => $this->requester_id,
            'target_id' => $this->target_id,
            'status' => $this->status,
            'message' => $this->message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'requester_booking' => BookingResource::make($this->whenLoaded('requesterBooking')),
            'target_booking' => BookingResource::make($this->whenLoaded('targetBooking')),
            'requester' => UserResource::make($this->whenLoaded('requester')),
            'target' => UserResource::make($this->whenLoaded('target')),
        ];
    }
}
