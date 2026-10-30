<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    /**
     * @return array<string, int|string|array<string, int|string>>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'product' => ProductRefResource::make($this->product)->resolve($request),
            'receiving_document' => [
                'id' => $this->document->id,
                'document_number' => $this->document->document_number,
            ],
            'receiving_item_id' => $this->receiving_item_id,
            'receipt_id' => $this->receipt_id,
            'created_by' => UserRefResource::make($this->creator)->resolve($request),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
