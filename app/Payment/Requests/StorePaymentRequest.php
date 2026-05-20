<?php

namespace App\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'method' => 'required|in:stripe,bank_transfer,cash,other',
            'status' => 'nullable|in:pending,completed,failed,refunded',
            'due_date' => 'nullable|date',
            'paid_at' => 'nullable|date_time',
            'notes' => 'nullable|string',
        ];
    }
}
