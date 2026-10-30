<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingDocumentSummaryResource extends JsonResource
{
    /**
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'supplier_name' => $this->supplier_name,
            'expected_date' => $this->expected_date->toDateString(),
            'status' => $this->status->value,
            'item_count' => (int) $this->items_count,
            'total_expected_quantity' => (int) ($this->total_expected_quantity ?? 0),
            'total_received_quantity' => (int) ($this->total_received_quantity ?? 0),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
