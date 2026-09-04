<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\InventoryRecordController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\CashCountController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']); // admin/manager account creation
Route::post('/login', [AuthController::class, 'login']);        // admin/manager email+password
Route::post('/store-login', [AuthController::class, 'storeLogin']); // crew: branch_id + store_code
Route::get('/public/branches', [BranchController::class, 'publicIndex']); // id+name only, for login dropdown

// Authenticated (requires Bearer token - works for both User and Branch tokens)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Branches - full detail (incl. store_code) restricted to admin/manager only
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/branches', [BranchController::class, 'index']);
        Route::get('/branches/{branch}', [BranchController::class, 'show']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);
    });

    // Items - anyone logged in (store token or user) can view, admin/manager can manage
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{item}', [ItemController::class, 'show']);
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('/items', [ItemController::class, 'store']);
        Route::put('/items/{item}', [ItemController::class, 'update']);
        Route::delete('/items/{item}', [ItemController::class, 'destroy']);
    });

    // Inventory records - store token (crew) or admin/manager can create/view,
    // only admin/manager can edit/delete
    Route::get('/inventory-records/shift-preview', [InventoryRecordController::class, 'shiftPreview']);
    Route::post('/inventory-records/bulk', [InventoryRecordController::class, 'storeBulk']);
    Route::post('/inventory-records/check-shift', [InventoryRecordController::class, 'checkShift']);
    Route::get('/inventory-records', [InventoryRecordController::class, 'index']);
    Route::post('/inventory-records', [InventoryRecordController::class, 'store']);
    Route::get('/inventory-records/{inventoryRecord}', [InventoryRecordController::class, 'show']);
    Route::middleware('role:admin,manager')->group(function () {
        Route::put('/inventory-records/{inventoryRecord}', [InventoryRecordController::class, 'update']);
        Route::delete('/inventory-records/{inventoryRecord}', [InventoryRecordController::class, 'destroy']);
    });

    Route::get('/cash-counts', [CashCountController::class, 'show']);
    Route::post('/cash-counts', [CashCountController::class, 'store']);
});