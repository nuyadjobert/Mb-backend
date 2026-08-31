<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Branch extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'address',
        'contact_number',
        'is_active',
        'store_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inventoryRecords(): HasMany
    {
        return $this->hasMany(InventoryRecord::class);
    }
}