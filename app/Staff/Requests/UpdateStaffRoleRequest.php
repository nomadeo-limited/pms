<?php

namespace App\Staff\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'required|in:organizer_admin,organizer_staff',
        ];
    }
}
