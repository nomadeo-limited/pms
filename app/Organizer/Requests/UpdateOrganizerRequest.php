<?php

namespace App\Organizer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:organizers,email,' . $this->route('id'),
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|size:2',
            'currency' => 'nullable|string|size:3',
            'timezone' => 'nullable|string|max:50',
            'locale' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
