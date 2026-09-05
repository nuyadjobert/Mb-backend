<?php

namespace App\Services;

use App\Models\InventoryRecord;
use App\Models\Item;
use Carbon\Carbon;

class InventoryCalculationService
{
    /**
     * Resolve the "beginning_qty" for a shift automatically.
     *
     * - Shift 2 & 3: pulled from the SAME day's previous shift ending_qty.
     * - Shift 1: pulled from the PREVIOUS day's Shift 3 ending_qty.
     *   If no prior record exists (e.g. very first entry ever), defaults to 0
     *   and should be manually corrected by a manager.
     */
    public function resolveBeginningQty(int $branchId, int $itemId, int $shiftNumber, string $recordDate): float
    {
        if ($shiftNumber > 1) {
            $previous = InventoryRecord::where('branch_id', $branchId)
                ->where('item_id', $itemId)
                ->where('record_date', $recordDate)
                ->where('shift_number', $shiftNumber - 1)
                ->first();

            return $previous ? (float) $previous->ending_qty : 1000.0;
        }

        // Shift 1 -> carry from previous day's Shift 3
        $previousDay = Carbon::parse($recordDate)->subDay()->toDateString();

        $previous = InventoryRecord::where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('record_date', $previousDay)
            ->where('shift_number', 3)
            ->first();

        return $previous ? (float) $previous->ending_qty : 1000.0;
    }

    /**
     * Compute usage_qty, total_order and total_sales for a record.
     *
     * usage_qty   = beginning_qty + del_qty - out_qty - ending_qty
     * total_order = usage_qty / item.divisor      (divisor default 2)
     * total_sales = total_order * item.price
     */
    public function calculate(array $data, Item $item): array
    {
        $beginning = (float) $data['beginning_qty'];
        $del = (float) ($data['del_qty'] ?? 0);
        $out = (float) ($data['out_qty'] ?? 0);
        $ending = (float) $data['ending_qty'];

        $usage = $beginning + $del - $out - $ending;

        $divisor = (float) $item->divisor > 0 ? (float) $item->divisor : 1;
        $totalOrder = $usage / $divisor;
        $totalSales = $totalOrder * (float) $item->price;

        return [
            'beginning_qty' => round($beginning, 2),
            'del_qty' => round($del, 2),
            'out_qty' => round($out, 2),
            'ending_qty' => round($ending, 2),
            'usage_qty' => round($usage, 2),
            'total_order' => round($totalOrder, 2),
            'total_sales' => round($totalSales, 2),
        ];
    }

    public function validateDivisibility(float $usageQty, Item $item): ?string
    {
        $divisor = (float) $item->divisor;

        if ($divisor <= 1) {
            return null; // no constraint for items with no meaningful divisor
        }

        $remainder = fmod($usageQty, $divisor);

        // Tolerate floating point noise (e.g. 0.999999999 vs 1)
        $isDivisible = abs($remainder) < 0.001 || abs($remainder - $divisor) < 0.001;

        if (! $isDivisible) {
            return sprintf(
                '"%s": usage quantity (%s) is not evenly divisible by %s. Please recheck Beginning/Del/Out/Ending.',
                $item->name,
                rtrim(rtrim(number_format($usageQty, 2), '0'), '.'),
                rtrim(rtrim(number_format($divisor, 2), '0'), '.')
            );
        }

        return null;
    }
}