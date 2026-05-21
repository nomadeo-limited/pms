<?php

namespace App\Availability\Requests;

use App\Availability\Helpers\WeekdayMask;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->check_in_days)) {
            $this->merge(['check_in_days' => WeekdayMask::fromArray($this->check_in_days)]);
        }
        if (is_array($this->check_out_days)) {
            $this->merge(['check_out_days' => WeekdayMask::fromArray($this->check_out_days)]);
        }
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
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
