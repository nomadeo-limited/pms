<?php

namespace App\Program\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'required|uuid|exists:properties,id',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_per_booking' => 'nullable|integer|min:0',
        ];
    }
}
