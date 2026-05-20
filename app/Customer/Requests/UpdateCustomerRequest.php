<?php

namespace App\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|size:2',
            'date_of_birth' => 'nullable|date',
            'passport_number' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'dietary_restrictions' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blacklisted',
            'preferred_locale' => 'nullable|string|max:10',
            'preferred_currency' => 'nullable|string|size:3',
        ];
    }
}
