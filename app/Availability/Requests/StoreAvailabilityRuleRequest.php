<?php

namespace App\Availability\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvailabilityRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ruleable_type' => 'required|in:program,unit',
            'ruleable_id' => 'required|uuid',
            'rule_type' => 'required|in:daily,specific_dates,date_range',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'weekday_mask' => 'nullable|string|size:7',
            'is_start_date' => 'nullable|boolean',
            'capacity' => 'nullable|integer|min:0',
        ];
    }
}
