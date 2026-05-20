<?php

namespace App\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'nullable|uuid|exists:properties,id',
            'program_id' => 'nullable|uuid|exists:programs,id',
            'type' => 'required|in:full_upfront,deposit_then_balance,installments',
            'deposit_percentage' => 'nullable|numeric|between:0,100',
            'balance_due_days_before' => 'nullable|integer|min:0',
        ];
    }
}
