<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'shift_number',
        'record_date',
        'pieces_1000',
        'pieces_500',
        'pieces_100',
        'pieces_50',
        'pieces_20',
        'pieces_10',
        'pieces_5',
        'pieces_1',
        'serials_1000',
        'serials_500',
        'total_cash',
        'total_expenses',
        'net_cash',
        'crew_name',
        'notes',
        'finalized_at',
        'finalized_by',
    ];

    protected $casts = [
        'record_date' => 'date',
        'serials_1000' => 'array',
        'serials_500' => 'array',
        'total_cash' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'net_cash' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}