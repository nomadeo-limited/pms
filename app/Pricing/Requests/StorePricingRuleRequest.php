<?php

namespace App\Pricing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priceable_type' => 'required|in:program,unit,add_on',
            'priceable_id' => 'required|uuid',
            'name' => 'required|string|max:255',
            'model' => 'required|in:per_night,per_person_per_night,fixed_package',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:0',
        ];
    }
}
