<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivingDocumentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('supplier_name'))) {
            $this->merge(['supplier_name' => trim($this->input('supplier_name'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'expected_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.expected_quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
