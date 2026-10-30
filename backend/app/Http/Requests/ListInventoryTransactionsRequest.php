<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListInventoryTransactionsRequest extends FormRequest
{
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'product_id' => ['sometimes', 'integer', 'min:1'],
            'receiving_document_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
