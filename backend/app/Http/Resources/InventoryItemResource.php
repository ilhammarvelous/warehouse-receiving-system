<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'sku' => $this->product->sku,
            'name' => $this->product->name,
            'quantity_on_hand' => $this->quantity_on_hand,
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
