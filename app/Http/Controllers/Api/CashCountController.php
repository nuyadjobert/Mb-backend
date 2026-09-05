<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashCount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashCountController extends Controller
{
    private const DENOMINATIONS = [1000, 500, 100, 50, 20, 10, 5, 1];

    /** Fetch an existing cash count for a shift/date, if one was already submitted. */
    public function show(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $branchId = $this->resolveBranchId($authUser, $validated);
        if ($branchId instanceof JsonResponse) {
            return $branchId;
        }

        $cashCount = CashCount::where('branch_id', $branchId)
            ->where('shift_number', $validated['shift_number'])
            ->where('record_date', $validated['record_date'])
            ->first();

        return response()->json($cashCount);
    }

    public function store(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'crew_name' => 'nullable|string|max:255',
            'pieces_1000' => 'nullable|integer|min:0',
            'pieces_500' => 'nullable|integer|min:0',
            'pieces_100' => 'nullable|integer|min:0',
            'pieces_50' => 'nullable|integer|min:0',
            'pieces_20' => 'nullable|integer|min:0',
            'pieces_10' => 'nullable|integer|min:0',
            'pieces_5' => 'nullable|integer|min:0',
            'pieces_1' => 'nullable|integer|min:0',
            'serials_1000' => 'nullable|array',
            'serials_1000.*' => 'nullable|string|max:100',
            'serials_500' => 'nullable|array',
            'serials_500.*' => 'nullable|string|max:100',
            'total_expenses' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $branchId = $this->resolveBranchId($authUser, $validated);
        if ($branchId instanceof JsonResponse) {
            return $branchId;
        }

        $pieces = [];
        foreach (self::DENOMINATIONS as $denom) {
            $pieces[$denom] = $validated["pieces_{$denom}"] ?? 0;
        }

        $totalCash = 0;
        foreach ($pieces as $denom => $count) {
            $totalCash += $denom * $count;
        }

        $totalExpenses = $validated['total_expenses'] ?? 0;
        $netCash = $totalCash - $totalExpenses;

        $cashCount = CashCount::updateOrCreate(
            [
                'branch_id' => $branchId,
                'shift_number' => $validated['shift_number'],
                'record_date' => $validated['record_date'],
            ],
            [
                'pieces_1000' => $pieces[1000],
                'pieces_500' => $pieces[500],
                'pieces_100' => $pieces[100],
                'pieces_50' => $pieces[50],
                'pieces_20' => $pieces[20],
                'pieces_10' => $pieces[10],
                'pieces_5' => $pieces[5],
                'pieces_1' => $pieces[1],
                'serials_1000' => $validated['serials_1000'] ?? [],
                'serials_500' => $validated['serials_500'] ?? [],
                'total_cash' => $totalCash,
                'total_expenses' => $totalExpenses,
                'net_cash' => $netCash,
                'crew_name' => $validated['crew_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json($cashCount, 201);
    }

    /**
     * Crew's final approval after reviewing the summary. Locks in this
     * shift's cash count as the approved version.
     */
    public function finalize(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'finalized_by' => 'required|string|max:255',
        ]);

        $branchId = $this->resolveBranchId($authUser, $validated);
        if ($branchId instanceof JsonResponse) {
            return $branchId;
        }

        $cashCount = CashCount::where('branch_id', $branchId)
            ->where('shift_number', $validated['shift_number'])
            ->where('record_date', $validated['record_date'])
            ->first();

        if (! $cashCount) {
            return response()->json([
                'message' => 'Cash count not found for this shift. Please save it first.',
            ], 404);
        }

        $cashCount->update([
            'finalized_at' => now(),
            'finalized_by' => $validated['finalized_by'],
        ]);

        return response()->json($cashCount);
    }

    /**
     * @return int|JsonResponse Branch ID, or a JsonResponse error to return immediately.
     */
    private function resolveBranchId($authUser, array $validated)
    {
        if ($authUser instanceof Branch) {
            return $authUser->id;
        }

        if ($authUser instanceof User && ($authUser->isAdmin() || $authUser->isManager())) {
            $branchId = $validated['branch_id'] ?? $authUser->branch_id;

            if (! $branchId) {
                return response()->json([
                    'message' => 'branch_id is required when submitting as admin/manager without an assigned branch.',
                ], 422);
            }

            return $branchId;
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}