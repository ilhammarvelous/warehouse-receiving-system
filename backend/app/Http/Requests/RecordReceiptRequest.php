<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordReceiptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'Idempotency-Key' => $this->header('Idempotency-Key'),
        ]);
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
            'quantity' => ['required', 'integer', 'min:1'],
            'Idempotency-Key' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }
}
