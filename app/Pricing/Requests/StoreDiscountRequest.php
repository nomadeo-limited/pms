<?php

namespace App\Pricing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:50',
            'type' => 'required|in:percentage,fixed_amount,early_bird,last_minute,long_stay',
            'value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'min_nights' => 'nullable|integer|min:1',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
        ];
    }
}
