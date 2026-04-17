<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Preserves the existing JSON shape (flat model attributes).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lot_id' => $this->lot_id,
            'slot_id' => $this->slot_id,
            'booking_type' => $this->booking_type,
            'lot_name' => $this->lot_name,
            'slot_number' => $this->slot_number,
            'vehicle_plate' => $this->vehicle_plate,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'notes' => $this->notes,
            'recurrence' => $this->recurrence,
            'checked_in_at' => $this->checked_in_at,
            'base_price' => $this->base_price,
            'tax_amount' => $this->tax_amount,
            'total_price' => $this->total_price,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => UserResource::make($this->whenLoaded('user')),
            'lot' => ParkingLotResource::make($this->whenLoaded('lot')),
            'slot' => ParkingSlotResource::make($this->whenLoaded('slot')),
            'booking_notes' => BookingNoteResource::collection($this->whenLoaded('bookingNotes')),
        ];
    }
}
