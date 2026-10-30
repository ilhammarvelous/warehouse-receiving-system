<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Receipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'receiving_item_id',
        'quantity',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ReceivingItem::class, 'receiving_item_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inventoryTransaction(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class);
    }

    public function idempotencyKey(): HasOne
    {
        return $this->hasOne(IdempotencyKey::class);
    }
}
