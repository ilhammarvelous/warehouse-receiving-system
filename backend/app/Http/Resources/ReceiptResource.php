<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, int|string|array<string, int|string>>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->receiving_item_id,
            'quantity' => $this->quantity,
            'received_by' => UserRefResource::make($this->receiver)->resolve($request),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
