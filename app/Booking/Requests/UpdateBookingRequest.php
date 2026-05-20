<?php

namespace App\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:pending,confirmed,checked_in,checked_out,cancelled,no_show',
            'payment_status' => 'sometimes|in:unpaid,partial,paid,refunded',
            'notes' => 'nullable|string',
            'total_price' => 'sometimes|numeric|min:0',
        ];
    }
}
