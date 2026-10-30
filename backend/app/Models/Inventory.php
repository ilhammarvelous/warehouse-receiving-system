<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    public const CREATED_AT = null;

    protected $table = 'inventory';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'quantity_on_hand' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
