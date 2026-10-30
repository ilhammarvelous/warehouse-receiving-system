<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        // BR-01: Canonicalize SKUs before validating so case-insensitive uniqueness is consistent.
        if (is_string($this->input('sku'))) {
            $normalized['sku'] = strtoupper(trim($this->input('sku')));
        }

        if (is_string($this->input('name'))) {
            $normalized['name'] = trim($this->input('name'));
        }

        $this->merge($normalized);
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
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]([A-Z0-9-]*[A-Z0-9])?$/'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
