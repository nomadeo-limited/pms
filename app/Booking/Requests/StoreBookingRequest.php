<?php

namespace App\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'required|uuid|exists:properties,id',
            'customer_id' => 'required|uuid|exists:customers,id',
            'program_id' => 'nullable|uuid|exists:programs,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'guests' => 'nullable|integer|min:1',
            'total_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'discount_id' => 'nullable|uuid|exists:discounts,id',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'source' => 'nullable|in:direct,marketplace,channel_manager,walk_in',
            'unit_ids' => 'required_without:program_id|nullable|array|min:1',
            'unit_ids.*.unit_id' => 'required|uuid|exists:units,id',
            'unit_ids.*.guests' => 'nullable|integer|min:1',
            'unit_ids.*.price_per_night' => 'nullable|numeric|min:0',
            'add_on_ids' => 'nullable|array',
            'add_on_ids.*.add_on_id' => 'required|uuid|exists:add_ons,id',
            'add_on_ids.*.quantity' => 'nullable|integer|min:1',
            'add_on_ids.*.unit_price' => 'nullable|numeric|min:0',
            'additional_guests' => 'nullable|array',
            'additional_guests.*.first_name' => 'required|string|max:100',
            'additional_guests.*.last_name' => 'required|string|max:100',
            'additional_guests.*.email' => 'nullable|email',
            'additional_guests.*.phone' => 'nullable|string|max:50',
            'additional_guests.*.nationality' => 'nullable|string|size:2',
            'additional_guests.*.date_of_birth' => 'nullable|date',
            'additional_guests.*.document_type' => 'nullable|in:passport,national_id,other',
            'additional_guests.*.document_number' => 'nullable|string|max:50',
            'additional_guests.*.document_country' => 'nullable|string|size:2',
        ];
    }
}
