<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDynamicPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'enabled' => 'sometimes|boolean',
            'base_price' => 'sometimes|numeric|min:0',
            'surge_multiplier' => 'sometimes|numeric|min:1|max:10',
            'discount_multiplier' => 'sometimes|numeric|min:0.1|max:1',
            'surge_threshold' => 'sometimes|integer|min:1|max:100',
            'discount_threshold' => 'sometimes|integer|min:0|max:99',
        ];
    }
}
