<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\ReceivingDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ReceivingDocument extends Model
{
    /** @use HasFactory<ReceivingDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'document_number',
        'supplier_name',
        'expected_date',
        'notes',
        'status',
        'created_by',
        'status_changed_by',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_date' => 'date',
            'status' => DocumentStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceivingItem::class);
    }

    public function receipts(): HasManyThrough
    {
        return $this->hasManyThrough(Receipt::class, ReceivingItem::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
