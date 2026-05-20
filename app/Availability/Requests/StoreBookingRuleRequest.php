<?php

namespace App\Availability\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'nullable|uuid|exists:properties,id',
            'program_id' => 'nullable|uuid|exists:programs,id',
            'min_nights' => 'nullable|integer|min:1',
            'max_nights' => 'nullable|integer|min:1',
            'check_in_days' => 'nullable|string|size:7',
            'check_out_days' => 'nullable|string|size:7',
            'min_advance_days' => 'nullable|integer|min:0',
            'max_advance_days' => 'nullable|integer|min:0',
        ];
    }
}
