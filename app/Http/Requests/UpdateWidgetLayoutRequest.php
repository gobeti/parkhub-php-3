<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWidgetLayoutRequest extends FormRequest
{
    private const WIDGET_TYPES = [
        'occupancy_chart',
        'revenue_summary',
        'recent_bookings',
        'user_growth',
        'booking_heatmap',
        'active_alerts',
        'maintenance_status',
        'ev_charging_status',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|string',
            'widgets.*.widget_type' => 'required|string|in:'.implode(',', self::WIDGET_TYPES),
            'widgets.*.position' => 'required|array',
            'widgets.*.visible' => 'required|boolean',
        ];
    }
}
