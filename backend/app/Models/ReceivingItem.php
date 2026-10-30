<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivingItem extends Model
{
    protected $fillable = [
        'receiving_document_id',
        'product_id',
        'expected_quantity',
        'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'received_quantity' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ReceivingDocument::class, 'receiving_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
