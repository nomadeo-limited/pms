<?php

namespace App\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_published' => 'sometimes|boolean',
            'comment' => 'nullable|string',
        ];
    }
}
