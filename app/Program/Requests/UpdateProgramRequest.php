<?php

namespace App\Program\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'duration_days' => 'nullable|integer|min:1',
            'images' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
