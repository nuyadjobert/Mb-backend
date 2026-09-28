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

        /*
        |--------------------------------------------------------------------------
        | Prepare serial numbers
        |--------------------------------------------------------------------------
        |
        | Remove empty values and normalize serial numbers:
        | - Remove leading/trailing spaces
        | - Convert to uppercase
        |
        */

        $serials1000 = array_values(array_filter(
            $validated['serials_1000'] ?? [],
            fn ($serial) => trim((string) $serial) !== ''
        ));

        $serials500 = array_values(array_filter(
            $validated['serials_500'] ?? [],
            fn ($serial) => trim((string) $serial) !== ''
        ));

        $serials1000 = array_map(
            fn ($serial) => strtoupper(trim((string) $serial)),
            $serials1000
        );

        $serials500 = array_map(
            fn ($serial) => strtoupper(trim((string) $serial)),
            $serials500
        );

        /*
        |--------------------------------------------------------------------------
        | Get declared number of large bills
        |--------------------------------------------------------------------------
        */

        $pieces1000 = $validated['pieces_1000'] ?? 0;
        $pieces500 = $validated['pieces_500'] ?? 0;

        /*
        |--------------------------------------------------------------------------
        | Validate number of serial numbers
        |--------------------------------------------------------------------------
        |
        | If crew says there are 3 x ₱1,000 bills,
        | there must be exactly 3 serial numbers.
        |
        */

        if (count($serials1000) !== $pieces1000) {
            return response()->json([
                'message' => 'The number of ₱1,000 serial numbers does not match the number of ₱1,000 bills.',
                'errors' => [
                    'serials_1000' => [
                        "Expected {$pieces1000} serial number(s), but received "
                        . count($serials1000) . '.',
                    ],
                ],
            ], 422);
        }

        if (count($serials500) !== $pieces500) {
            return response()->json([
                'message' => 'The number of ₱500 serial numbers does not match the number of ₱500 bills.',
                'errors' => [
                    'serials_500' => [
                        "Expected {$pieces500} serial number(s), but received "
                        . count($serials500) . '.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Detect duplicate ₱1,000 serial numbers
        |--------------------------------------------------------------------------
        */

        if (count($serials1000) !== count(array_unique($serials1000))) {
            $duplicates = array_values(
                array_unique(
                    array_diff_assoc(
                        $serials1000,
                        array_unique($serials1000)
                    )
                )
            );

            return response()->json([
                'message' => 'Duplicate ₱1,000 serial number detected.',
                'errors' => [
                    'serials_1000' => [
                        'The following ₱1,000 serial number(s) appear more than once: '
                        . implode(', ', $duplicates),
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Detect duplicate ₱500 serial numbers
        |--------------------------------------------------------------------------
        */

        if (count($serials500) !== count(array_unique($serials500))) {
            $duplicates = array_values(
                array_unique(
                    array_diff_assoc(
                        $serials500,
                        array_unique($serials500)
                    )
                )
            );

            return response()->json([
                'message' => 'Duplicate ₱500 serial number detected.',
                'errors' => [
                    'serials_500' => [
                        'The following ₱500 serial number(s) appear more than once: '
                        . implode(', ', $duplicates),
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Detect same serial number used for different denominations
        |--------------------------------------------------------------------------
        */

        $duplicateAcrossDenominations = array_values(
            array_intersect($serials1000, $serials500)
        );

        if (!empty($duplicateAcrossDenominations)) {
            return response()->json([
                'message' => 'The same serial number cannot be used for different denominations.',
                'errors' => [
                    'serials' => [
                        'The following serial number(s) were entered for both '
                        . '₱1,000 and ₱500 bills: '
                        . implode(', ', $duplicateAcrossDenominations),
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate cash totals
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Save cash count
        |--------------------------------------------------------------------------
        */

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

                'serials_1000' => $serials1000,
                'serials_500' => $serials500,

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
     * Crew's final approval after reviewing the summary.
     * Locks in this shift's cash count as the approved version.
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
     * @return int|JsonResponse
     * Branch ID, or a JsonResponse error to return immediately.
     */
    private function resolveBranchId($authUser, array $validated)
    {
        if ($authUser instanceof Branch) {
            return $authUser->id;
        }

        if (
            $authUser instanceof User &&
            ($authUser->isAdmin() || $authUser->isManager())
        ) {
            $branchId = $validated['branch_id'] ?? $authUser->branch_id;

            if (! $branchId) {
                return response()->json([
                    'message' => 'branch_id is required when submitting as admin/manager without an assigned branch.',
                ], 422);
            }

            return $branchId;
        }

        return response()->json([
            'message' => 'Unauthorized.',
        ], 403);
    }
}