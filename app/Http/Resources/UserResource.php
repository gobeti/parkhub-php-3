<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Respects the model's $hidden (password, remember_token).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'picture' => $this->picture,
            'phone' => $this->phone,
            'role' => $this->role,
            'preferences' => $this->preferences,
            'is_active' => $this->is_active,
            'department' => $this->department,
            'last_login' => $this->last_login,
            'credits_balance' => $this->credits_balance,
            'credits_monthly_quota' => $this->credits_monthly_quota,
            'credits_last_refilled' => $this->credits_last_refilled,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
