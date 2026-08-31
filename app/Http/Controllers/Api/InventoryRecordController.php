<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryRecord;
use App\Models\Item;
use App\Models\User;
use App\Services\InventoryCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRecordController extends Controller
{
    public function __construct(protected InventoryCalculationService $calculator)
    {
    }

    public function index(Request $request)
    {
        $query = InventoryRecord::with(['item', 'branch', 'user']);

        $authUser = $request->user();

        if ($authUser instanceof Branch) {
            $query->where('branch_id', $authUser->id);
        } elseif ($authUser instanceof User) {
            if (! $authUser->isAdmin()) {
                $query->where('branch_id', $authUser->branch_id);
            } elseif ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('shift_number')) {
            $query->where('shift_number', $request->shift_number);
        }

        if ($request->filled('record_date')) {
            $query->whereDate('record_date', $request->record_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->orderBy('record_date', 'desc')
            ->orderBy('shift_number')
            ->paginate(20);

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'del_qty' => 'nullable|numeric|min:0',
            'out_qty' => 'nullable|numeric|min:0',
            'ending_qty' => 'required|numeric|min:0',
            'beginning_qty' => 'nullable|numeric|min:0',
            'beginning_override_reason' => 'nullable|string|max:255',
            'crew_name' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $userId = null;

        if ($authUser instanceof Branch) {
            $branchId = $authUser->id;
        } elseif ($authUser instanceof User && ($authUser->isAdmin() || $authUser->isManager())) {
            $branchId = $validated['branch_id'] ?? $authUser->branch_id;
            $userId = $authUser->id;

            if (! $branchId) {
                return response()->json([
                    'message' => 'branch_id is required when submitting as admin/manager without an assigned branch.',
                ], 422);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $item = Item::findOrFail($validated['item_id']);

        $autoBeginning = $this->calculator->resolveBeginningQty(
            $branchId,
            $item->id,
            $validated['shift_number'],
            $validated['record_date']
        );

        $beginningQty = $validated['beginning_qty'] ?? $autoBeginning;

        if (abs($beginningQty - $autoBeginning) > 0.001 && empty($validated['beginning_override_reason'])) {
            throw ValidationException::withMessages([
                'beginning_override_reason' => ["A reason is required when correcting the beginning qty for \"{$item->name}\"."],
            ]);
        }

        $computed = $this->calculator->calculate([
            'beginning_qty' => $beginningQty,
            'del_qty' => $validated['del_qty'] ?? 0,
            'out_qty' => $validated['out_qty'] ?? 0,
            'ending_qty' => $validated['ending_qty'],
        ], $item);

        $record = InventoryRecord::create([
            'branch_id' => $branchId,
            'item_id' => $item->id,
            'user_id' => $userId,
            'crew_name' => $validated['crew_name'],
            'shift_number' => $validated['shift_number'],
            'record_date' => $validated['record_date'],
            'notes' => $validated['notes'] ?? null,
            'beginning_qty_auto' => $autoBeginning,
            'beginning_override_reason' => $validated['beginning_override_reason'] ?? null,
            ...$computed,
        ]);

        return response()->json($record->load(['item', 'branch', 'user']), 201);
    }

    public function shiftPreview(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($authUser instanceof Branch) {
            $branchId = $authUser->id;
        } elseif ($authUser instanceof User) {
            $branchId = ($authUser->isAdmin() && $request->filled('branch_id'))
                ? $validated['branch_id']
                : $authUser->branch_id;

            if (! $branchId) {
                return response()->json(['message' => 'branch_id is required.'], 422);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $items = Item::where('is_active', true)->orderBy('sort_order')->get();

        $existingRecords = InventoryRecord::where('branch_id', $branchId)
            ->where('shift_number', $validated['shift_number'])
            ->where('record_date', $validated['record_date'])
            ->get()
            ->keyBy('item_id');

        $preview = $items->map(function (Item $item) use ($branchId, $validated, $existingRecords) {
            $existing = $existingRecords->get($item->id);

            $beginningQty = $existing
                ? (float) $existing->beginning_qty
                : $this->calculator->resolveBeginningQty(
                    $branchId,
                    $item->id,
                    $validated['shift_number'],
                    $validated['record_date']
                );

            return [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'unit' => $item->unit,
                'price' => (float) $item->price,
                'divisor' => (float) $item->divisor,
                'beginning_qty' => $beginningQty,
                'existing_record' => $existing,
            ];
        });

        return response()->json([
            'branch_id' => $branchId,
            'shift_number' => $validated['shift_number'],
            'record_date' => $validated['record_date'],
            'items' => $preview,
        ]);
    }

    public function storeBulk(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'crew_name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id|distinct',
            'items.*.del_qty' => 'nullable|numeric|min:0',
            'items.*.out_qty' => 'nullable|numeric|min:0',
            'items.*.ending_qty' => 'required|numeric|min:0',
            'items.*.beginning_qty' => 'nullable|numeric|min:0',
            'items.*.beginning_override_reason' => 'nullable|string|max:255',
        ]);

        $userId = null;

        if ($authUser instanceof Branch) {
            $branchId = $authUser->id;
        } elseif ($authUser instanceof User && ($authUser->isAdmin() || $authUser->isManager())) {
            $branchId = $validated['branch_id'] ?? $authUser->branch_id;
            $userId = $authUser->id;

            if (! $branchId) {
                return response()->json([
                    'message' => 'branch_id is required when submitting as admin/manager without an assigned branch.',
                ], 422);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $records = DB::transaction(function () use ($validated, $branchId, $userId) {
            $saved = [];

            foreach ($validated['items'] as $row) {
                $item = Item::findOrFail($row['item_id']);

                $autoBeginning = $this->calculator->resolveBeginningQty(
                    $branchId,
                    $item->id,
                    $validated['shift_number'],
                    $validated['record_date']
                );

                $beginningQty = $row['beginning_qty'] ?? $autoBeginning;

                if (abs($beginningQty - $autoBeginning) > 0.001 && empty($row['beginning_override_reason'])) {
                    throw ValidationException::withMessages([
                        'items' => ["A reason is required when correcting the beginning qty for \"{$item->name}\"."],
                    ]);
                }

                $computed = $this->calculator->calculate([
                    'beginning_qty' => $beginningQty,
                    'del_qty' => $row['del_qty'] ?? 0,
                    'out_qty' => $row['out_qty'] ?? 0,
                    'ending_qty' => $row['ending_qty'],
                ], $item);

                $record = InventoryRecord::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'item_id' => $item->id,
                        'shift_number' => $validated['shift_number'],
                        'record_date' => $validated['record_date'],
                    ],
                    [
                        'user_id' => $userId,
                        'crew_name' => $validated['crew_name'],
                        'beginning_qty_auto' => $autoBeginning,
                        'beginning_override_reason' => $row['beginning_override_reason'] ?? null,
                        ...$computed,
                    ]
                );

                $saved[] = $record->load('item');
            }

            return $saved;
        });

        return response()->json([
            'branch_id' => $branchId,
            'shift_number' => $validated['shift_number'],
            'record_date' => $validated['record_date'],
            'crew_name' => $validated['crew_name'],
            'records' => $records,
            'shift_total_sales' => round(collect($records)->sum('total_sales'), 2),
        ], 201);
    }

    public function checkShift(Request $request)
    {
        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'checked_by' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $authUser = $request->user();

        if ($authUser instanceof Branch) {
            $branchId = $authUser->id;
        } elseif ($authUser instanceof User) {
            $branchId = $validated['branch_id'] ?? $authUser->branch_id;
            if (! $branchId) {
                return response()->json(['message' => 'branch_id is required.'], 422);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $updated = InventoryRecord::where('branch_id', $branchId)
            ->where('shift_number', $validated['shift_number'])
            ->whereDate('record_date', $validated['record_date'])
            ->update([
                'status' => 'checked',
                'checked_by' => $validated['checked_by'],
                'checked_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['message' => 'No records found for this shift/date.'], 404);
        }

        return response()->json(['message' => "Checked {$updated} record(s).", 'checked_count' => $updated]);
    }

    public function show(InventoryRecord $inventoryRecord)
    {
        return response()->json($inventoryRecord->load(['item', 'branch', 'user']));
    }

    public function update(Request $request, InventoryRecord $inventoryRecord)
    {
        $validated = $request->validate([
            'del_qty' => 'nullable|numeric|min:0',
            'out_qty' => 'nullable|numeric|min:0',
            'ending_qty' => 'sometimes|required|numeric|min:0',
            'beginning_qty' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $item = $inventoryRecord->item;

        $computed = $this->calculator->calculate([
            'beginning_qty' => $validated['beginning_qty'] ?? $inventoryRecord->beginning_qty,
            'del_qty' => $validated['del_qty'] ?? $inventoryRecord->del_qty,
            'out_qty' => $validated['out_qty'] ?? $inventoryRecord->out_qty,
            'ending_qty' => $validated['ending_qty'] ?? $inventoryRecord->ending_qty,
        ], $item);

        $inventoryRecord->update([
            'notes' => $validated['notes'] ?? $inventoryRecord->notes,
            ...$computed,
        ]);

        return response()->json($inventoryRecord->fresh()->load(['item', 'branch', 'user']));
    }

    public function destroy(InventoryRecord $inventoryRecord)
    {
        $inventoryRecord->delete();

        return response()->json(['message' => 'Record deleted.']);
    }
}