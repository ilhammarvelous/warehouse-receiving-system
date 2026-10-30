<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingItemResource extends JsonResource
{
    /**
     * @return array<string, int|array<string, int|string>>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => ProductRefResource::make($this->product)->resolve($request),
            'expected_quantity' => $this->expected_quantity,
            'received_quantity' => $this->received_quantity,
            'remaining_quantity' => $this->expected_quantity - $this->received_quantity,
        ];
    }
}
