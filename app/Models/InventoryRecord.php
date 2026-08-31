<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'item_id',
        'user_id',
        'crew_name',
        'status',
        'checked_by',
        'checked_at',
        'shift_number',
        'record_date',
        'beginning_qty',
        'beginning_qty_auto',
        'beginning_override_reason',
        'del_qty',
        'out_qty',
        'ending_qty',
        'usage_qty',
        'total_order',
        'total_sales',
        'notes',
    ];

    protected $casts = [
        'record_date' => 'date',
        'checked_at' => 'datetime',
        'beginning_qty' => 'decimal:2',
        'beginning_qty_auto' => 'decimal:2',
        'del_qty' => 'decimal:2',
        'out_qty' => 'decimal:2',
        'ending_qty' => 'decimal:2',
        'usage_qty' => 'decimal:2',
        'total_order' => 'decimal:2',
        'total_sales' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}