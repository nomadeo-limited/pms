<?php

namespace App\Customer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|uuid|exists:bookings,id|unique:reviews,booking_id',
            'customer_id' => 'required|uuid|exists:customers,id',
            'overall_rating' => 'required|integer|between:1,5',
            'accommodation_rating' => 'nullable|integer|between:1,5',
            'program_rating' => 'nullable|integer|between:1,5',
            'staff_rating' => 'nullable|integer|between:1,5',
            'value_rating' => 'nullable|integer|between:1,5',
            'comment' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ];
    }
}
