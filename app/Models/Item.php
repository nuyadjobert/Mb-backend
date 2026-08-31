<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'price',
        'divisor',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'divisor' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function inventoryRecords(): HasMany
    {
        return $this->hasMany(InventoryRecord::class);
    }
}