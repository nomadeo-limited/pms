<?php

namespace App\Customer\Requests;

use App\Tenant\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function __construct(private TenantContext $tenantContext)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizerId = $this->tenantContext->getOrganizerId();

        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('customers')->where('organizer_id', $organizerId)],
            'phone' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|size:2',
            'date_of_birth' => 'nullable|date',
            'document_type' => 'nullable|in:passport,national_id,other',
            'document_number' => 'nullable|string|max:50',
            'document_country' => 'nullable|string|size:2',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'dietary_restrictions' => 'nullable|string',
            'notes' => 'nullable|string',
            'preferred_locale' => 'nullable|string|max:10',
            'preferred_currency' => 'nullable|string|size:3',
        ];
    }
}
