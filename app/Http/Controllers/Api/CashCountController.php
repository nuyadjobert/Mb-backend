<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashCount;
use App\Models\InventoryRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashCountController extends Controller
{
    private const DENOMINATIONS = [
        1000,
        500,
        100,
        50,
        20,
        10,
        5,
        1,
    ];

    /**
     * =========================================================
     * CREW / EXISTING
     * =========================================================
     *
     * Fetch an existing cash count for a shift/date.
     */
    public function show(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' => 'required|integer|in:1,2,3',
            'record_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $branchId = $this->resolveBranchId(
            $authUser,
            $validated
        );

        if ($branchId instanceof JsonResponse) {
            return $branchId;
        }

        $cashCount = CashCount::where(
            'branch_id',
            $branchId
        )
            ->where(
                'shift_number',
                $validated['shift_number']
            )
            ->where(
                'record_date',
                $validated['record_date']
            )
            ->first();

        return response()->json($cashCount);
    }

    /**
     * =========================================================
     * CREW / EXISTING
     * =========================================================
     *
     * Save crew cash count.
     */
    public function store(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' =>
                'required|integer|in:1,2,3',

            'record_date' =>
                'required|date',

            'branch_id' =>
                'nullable|exists:branches,id',

            'crew_name' =>
                'nullable|string|max:255',

            'pieces_1000' =>
                'nullable|integer|min:0',

            'pieces_500' =>
                'nullable|integer|min:0',

            'pieces_100' =>
                'nullable|integer|min:0',

            'pieces_50' =>
                'nullable|integer|min:0',

            'pieces_20' =>
                'nullable|integer|min:0',

            'pieces_10' =>
                'nullable|integer|min:0',

            'pieces_5' =>
                'nullable|integer|min:0',

            'pieces_1' =>
                'nullable|integer|min:0',

            'serials_1000' =>
                'nullable|array',

            'serials_1000.*' =>
                'nullable|string|max:100',

            'serials_500' =>
                'nullable|array',

            'serials_500.*' =>
                'nullable|string|max:100',

            'total_expenses' =>
                'nullable|numeric|min:0',

            'notes' =>
                'nullable|string',
        ]);

        $branchId = $this->resolveBranchId(
            $authUser,
            $validated
        );

        if ($branchId instanceof JsonResponse) {
            return $branchId;
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare serial numbers
        |--------------------------------------------------------------------------
        */

        $serials1000 = array_values(
            array_filter(
                $validated['serials_1000'] ?? [],
                fn ($serial) =>
                    trim((string) $serial) !== ''
            )
        );

        $serials500 = array_values(
            array_filter(
                $validated['serials_500'] ?? [],
                fn ($serial) =>
                    trim((string) $serial) !== ''
            )
        );

        $serials1000 = array_map(
            fn ($serial) =>
                strtoupper(trim((string) $serial)),
            $serials1000
        );

        $serials500 = array_map(
            fn ($serial) =>
                strtoupper(trim((string) $serial)),
            $serials500
        );

        $pieces1000 =
            $validated['pieces_1000'] ?? 0;

        $pieces500 =
            $validated['pieces_500'] ?? 0;

        /*
        |--------------------------------------------------------------------------
        | Validate serial counts
        |--------------------------------------------------------------------------
        */

        if (
            count($serials1000) !==
            $pieces1000
        ) {
            return response()->json([
                'message' =>
                    'The number of ₱1,000 serial numbers does not match the number of ₱1,000 bills.',

                'errors' => [
                    'serials_1000' => [
                        "Expected {$pieces1000} serial number(s), but received "
                        . count($serials1000)
                        . '.',
                    ],
                ],
            ], 422);
        }

        if (
            count($serials500) !==
            $pieces500
        ) {
            return response()->json([
                'message' =>
                    'The number of ₱500 serial numbers does not match the number of ₱500 bills.',

                'errors' => [
                    'serials_500' => [
                        "Expected {$pieces500} serial number(s), but received "
                        . count($serials500)
                        . '.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate ₱1,000 serials
        |--------------------------------------------------------------------------
        */

        if (
            count($serials1000) !==
            count(array_unique($serials1000))
        ) {
            return response()->json([
                'message' =>
                    'Duplicate ₱1,000 serial number detected.',

                'errors' => [
                    'serials_1000' => [
                        'Duplicate ₱1,000 serial number detected.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate ₱500 serials
        |--------------------------------------------------------------------------
        */

        if (
            count($serials500) !==
            count(array_unique($serials500))
        ) {
            return response()->json([
                'message' =>
                    'Duplicate ₱500 serial number detected.',

                'errors' => [
                    'serials_500' => [
                        'Duplicate ₱500 serial number detected.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Cross denomination duplicate
        |--------------------------------------------------------------------------
        */

        $duplicateAcrossDenominations =
            array_values(
                array_intersect(
                    $serials1000,
                    $serials500
                )
            );

        if (
            ! empty(
                $duplicateAcrossDenominations
            )
        ) {
            return response()->json([
                'message' =>
                    'The same serial number cannot be used for different denominations.',

                'errors' => [
                    'serials' => [
                        'The following serial number(s) were entered for both ₱1,000 and ₱500 bills: '
                        . implode(
                            ', ',
                            $duplicateAcrossDenominations
                        ),
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate totals
        |--------------------------------------------------------------------------
        */

        $pieces = [];

        foreach (
            self::DENOMINATIONS as $denom
        ) {
            $pieces[$denom] =
                $validated["pieces_{$denom}"] ?? 0;
        }

        $totalCash = 0;

        foreach (
            $pieces as $denom => $count
        ) {
            $totalCash +=
                $denom * $count;
        }

        $totalExpenses =
            $validated['total_expenses'] ?? 0;

        $netCash =
            $totalCash - $totalExpenses;

        /*
        |--------------------------------------------------------------------------
        | Save crew submission
        |--------------------------------------------------------------------------
        */

        $cashCount =
            CashCount::updateOrCreate(
                [
                    'branch_id' => $branchId,

                    'shift_number' =>
                        $validated['shift_number'],

                    'record_date' =>
                        $validated['record_date'],
                ],
                [
                    'pieces_1000' =>
                        $pieces[1000],

                    'pieces_500' =>
                        $pieces[500],

                    'pieces_100' =>
                        $pieces[100],

                    'pieces_50' =>
                        $pieces[50],

                    'pieces_20' =>
                        $pieces[20],

                    'pieces_10' =>
                        $pieces[10],

                    'pieces_5' =>
                        $pieces[5],

                    'pieces_1' =>
                        $pieces[1],

                    'serials_1000' =>
                        $serials1000,

                    'serials_500' =>
                        $serials500,

                    'total_cash' =>
                        $totalCash,

                    'total_expenses' =>
                        $totalExpenses,

                    'net_cash' =>
                        $netCash,

                    'crew_name' =>
                        $validated['crew_name'] ?? null,

                    'notes' =>
                        $validated['notes'] ?? null,
                ]
            );

        return response()->json(
            $cashCount,
            201
        );
    }

    /**
     * =========================================================
     * HEAD CREW
     * =========================================================
     *
     * Retrieve the crew's submitted cash count together
     * with inventory sales and reconciliation information.
     */
    public function review(Request $request)
    {
        $authUser = $request->user();

        if (! $this->canReviewCashCount($authUser)) {
            return response()->json([
                'message' =>
                    'Only Head Crew or Management can review cash counts.',
            ], 403);
        }

        $validated = $request->validate([
            'shift_number' =>
                'required|integer|in:1,2,3',

            'record_date' =>
                'required|date',

            'branch_id' =>
                'nullable|exists:branches,id',
        ]);

        $branchId =
            $this->resolveReviewBranchId(
                $authUser,
                $validated
            );

        if (
            $branchId instanceof JsonResponse
        ) {
            return $branchId;
        }

        $cashCount =
            CashCount::where(
                'branch_id',
                $branchId
            )
                ->where(
                    'shift_number',
                    $validated['shift_number']
                )
                ->where(
                    'record_date',
                    $validated['record_date']
                )
                ->first();

        if (! $cashCount) {
            return response()->json([
                'message' =>
                    'The crew has not submitted a cash count for this shift yet.',
                'submitted' => false,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Inventory sales
        |--------------------------------------------------------------------------
        */

        $inventoryQuery =
            InventoryRecord::where(
                'branch_id',
                $branchId
            )
                ->where(
                    'shift_number',
                    $validated['shift_number']
                )
                ->where(
                    'record_date',
                    $validated['record_date']
                );

        $inventoryCount =
            (clone $inventoryQuery)->count();

        $checkedInventoryCount =
            (clone $inventoryQuery)
                ->where(
                    'status',
                    'checked'
                )
                ->count();

        $totalSales =
            (float) $inventoryQuery
                ->sum('total_sales');

        /*
        |--------------------------------------------------------------------------
        | Determine expenses used for review
        |--------------------------------------------------------------------------
        */

        $expenses =
            $cashCount->reviewed_expenses !== null
                ? (float) $cashCount->reviewed_expenses
                : (float) $cashCount->total_expenses;

        /*
        |--------------------------------------------------------------------------
        | Reconciliation
        |--------------------------------------------------------------------------
        */

        $expectedCash =
            round(
                $totalSales - $expenses,
                2
            );

        $actualCash =
            (float) $cashCount->total_cash;

        $variance =
            round(
                $actualCash - $expectedCash,
                2
            );

        $varianceStatus =
            $this->getVarianceStatus(
                $variance
            );

        return response()->json([
            'submitted' => true,

            'inventory_checked' =>
                $inventoryCount > 0 &&
                $inventoryCount ===
                    $checkedInventoryCount,

            'inventory_count' =>
                $inventoryCount,

            'checked_inventory_count' =>
                $checkedInventoryCount,

            'total_sales' =>
                round($totalSales, 2),

            'cash_count' =>
                $cashCount,

            'reviewed_expenses' =>
                $expenses,

            'expected_cash' =>
                $expectedCash,

            'actual_cash' =>
                round($actualCash, 2),

            'cash_variance' =>
                $variance,

            'variance_status' =>
                $varianceStatus,
        ]);
    }

    /**
     * =========================================================
     * HEAD CREW
     * =========================================================
     *
     * Save Head Crew review.
     *
     * Denominations and serial numbers CANNOT be changed here.
     */
    public function reviewUpdate(Request $request)
    {
        $authUser = $request->user();

        if (! $this->canReviewCashCount($authUser)) {
            return response()->json([
                'message' =>
                    'Only Head Crew or Management can review cash counts.',
            ], 403);
        }

        $validated = $request->validate([
            'shift_number' =>
                'required|integer|in:1,2,3',

            'record_date' =>
                'required|date',

            'branch_id' =>
                'nullable|exists:branches,id',

            'reviewed_by' =>
                'required|string|max:255',

            'reviewed_expenses' =>
                'required|numeric|min:0',

            'review_notes' =>
                'nullable|string|max:5000',
        ]);

        $branchId =
            $this->resolveReviewBranchId(
                $authUser,
                $validated
            );

        if (
            $branchId instanceof JsonResponse
        ) {
            return $branchId;
        }

        $cashCount =
            CashCount::where(
                'branch_id',
                $branchId
            )
                ->where(
                    'shift_number',
                    $validated['shift_number']
                )
                ->where(
                    'record_date',
                    $validated['record_date']
                )
                ->first();

        if (! $cashCount) {
            return response()->json([
                'message' =>
                    'The crew has not submitted a cash count for this shift yet.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Require inventory review first
        |--------------------------------------------------------------------------
        */

        $inventoryQuery =
            InventoryRecord::where(
                'branch_id',
                $branchId
            )
                ->where(
                    'shift_number',
                    $validated['shift_number']
                )
                ->where(
                    'record_date',
                    $validated['record_date']
                );

        $inventoryCount =
            (clone $inventoryQuery)->count();

        $checkedInventoryCount =
            (clone $inventoryQuery)
                ->where(
                    'status',
                    'checked'
                )
                ->count();

        if (
            $inventoryCount === 0 ||
            $inventoryCount !==
                $checkedInventoryCount
        ) {
            return response()->json([
                'message' =>
                    'All inventory records must be checked before the cash count can be confirmed.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate sales
        |--------------------------------------------------------------------------
        */

        $totalSales =
            (float) $inventoryQuery
                ->sum('total_sales');

        /*
        |--------------------------------------------------------------------------
        | Calculate reconciliation
        |--------------------------------------------------------------------------
        */

        $reviewedExpenses =
            (float)
            $validated['reviewed_expenses'];

        $expectedCash =
            round(
                $totalSales -
                $reviewedExpenses,
                2
            );

        $actualCash =
            (float) $cashCount->total_cash;

        $variance =
            round(
                $actualCash -
                $expectedCash,
                2
            );

        $varianceStatus =
            $this->getVarianceStatus(
                $variance
            );

        /*
        |--------------------------------------------------------------------------
        | Save Head Crew review
        |--------------------------------------------------------------------------
        */

        $cashCount->update([
            'reviewed_expenses' =>
                $reviewedExpenses,

            'expected_cash' =>
                $expectedCash,

            'cash_variance' =>
                $variance,

            'variance_status' =>
                $varianceStatus,

            'review_notes' =>
                $validated['review_notes'] ??
                null,

            'reviewed_by' =>
                $validated['reviewed_by'],

            'reviewed_at' =>
                now(),
        ]);

        return response()->json([
            'message' =>
                'Cash count reviewed successfully.',

            'cash_count' =>
                $cashCount->fresh(),

            'total_sales' =>
                round($totalSales, 2),

            'reviewed_expenses' =>
                $reviewedExpenses,

            'expected_cash' =>
                $expectedCash,

            'actual_cash' =>
                round($actualCash, 2),

            'cash_variance' =>
                $variance,

            'variance_status' =>
                $varianceStatus,
        ]);
    }

    /**
     * =========================================================
     * EXISTING FINALIZE
     * =========================================================
     */
    public function finalize(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'shift_number' =>
                'required|integer|in:1,2,3',

            'record_date' =>
                'required|date',

            'branch_id' =>
                'nullable|exists:branches,id',

            'finalized_by' =>
                'required|string|max:255',
        ]);

        $branchId =
            $this->resolveBranchId(
                $authUser,
                $validated
            );

        if (
            $branchId instanceof JsonResponse
        ) {
            return $branchId;
        }

        $cashCount =
            CashCount::where(
                'branch_id',
                $branchId
            )
                ->where(
                    'shift_number',
                    $validated['shift_number']
                )
                ->where(
                    'record_date',
                    $validated['record_date']
                )
                ->first();

        if (! $cashCount) {
            return response()->json([
                'message' =>
                    'Cash count not found for this shift. Please save it first.',
            ], 404);
        }

        $cashCount->update([
            'finalized_at' =>
                now(),

            'finalized_by' =>
                $validated['finalized_by'],
        ]);

        return response()->json(
            $cashCount
        );
    }

    /**
     * =========================================================
     * PERMISSION
     * =========================================================
     */
    private function canReviewCashCount(
        $authUser
    ): bool {
        if ($authUser instanceof Branch) {
            return
                $authUser->currentAccessToken()
                &&
                $authUser
                    ->currentAccessToken()
                    ->can('head_crew');
        }

        if ($authUser instanceof User) {
            return
                $authUser->isAdmin() ||
                $authUser->isManager();
        }

        return false;
    }

    /**
     * =========================================================
     * REVIEW BRANCH
     * =========================================================
     */
    private function resolveReviewBranchId(
        $authUser,
        array $validated
    ) {
        if ($authUser instanceof Branch) {
            return $authUser->id;
        }

        if (
            $authUser instanceof User &&
            (
                $authUser->isAdmin() ||
                $authUser->isManager()
            )
        ) {
            $branchId =
                $validated['branch_id'] ??
                $authUser->branch_id;

            if (! $branchId) {
                return response()->json([
                    'message' =>
                        'branch_id is required for management review.',
                ], 422);
            }

            return $branchId;
        }

        return response()->json([
            'message' =>
                'Unauthorized.',
        ], 403);
    }

    /**
     * =========================================================
     * VARIANCE STATUS
     * =========================================================
     */
    private function getVarianceStatus(
        float $variance
    ): string {
        if (abs($variance) < 0.01) {
            return 'EXACT';
        }

        return $variance < 0
            ? 'SHORT'
            : 'OVER';
    }

    /**
     * =========================================================
     * EXISTING BRANCH RESOLUTION
     * =========================================================
     */
    private function resolveBranchId(
        $authUser,
        array $validated
    ) {
        if ($authUser instanceof Branch) {
            return $authUser->id;
        }

        if (
            $authUser instanceof User &&
            (
                $authUser->isAdmin() ||
                $authUser->isManager()
            )
        ) {
            $branchId =
                $validated['branch_id'] ??
                $authUser->branch_id;

            if (! $branchId) {
                return response()->json([
                    'message' =>
                        'branch_id is required when submitting as admin/manager without an assigned branch.',
                ], 422);
            }

            return $branchId;
        }

        return response()->json([
            'message' =>
                'Unauthorized.',
        ], 403);
    }
}