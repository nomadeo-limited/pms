<?php

namespace App\Staff\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
