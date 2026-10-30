<?php

namespace App\Http\Resources;

use App\Models\Receipt;
use App\Models\ReceivingItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivingDocumentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...ReceivingDocumentSummaryResource::make($this->resource)->resolve($request),
            'notes' => $this->notes,
            'all_items_received' => $this->items->every(
                fn (ReceivingItem $item): bool => $item->received_quantity === $item->expected_quantity,
            ),
            'created_by' => UserRefResource::make($this->creator)->resolve($request),
            'status_changed_by' => $this->statusChangedBy === null
                ? null
                : UserRefResource::make($this->statusChangedBy)->resolve($request),
            'status_changed_at' => $this->status_changed_at?->toIso8601ZuluString(),
            'items' => $this->items
                ->map(fn (ReceivingItem $item): array => ReceivingItemResource::make($item)->resolve($request))
                ->all(),
            'receipts' => $this->receipts
                ->map(fn (Receipt $receipt): array => ReceiptResource::make($receipt)->resolve($request))
                ->all(),
        ];
    }
}
