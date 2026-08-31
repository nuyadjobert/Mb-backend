<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        return response()->json(Branch::orderBy('name')->get());
    }

    /**
     * Public, unauthenticated listing for the store-login screen.
     * Only exposes id/name -- never the store_code.
     */
    public function publicIndex()
    {
        return response()->json(
            Branch::where('is_active', true)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'store_code' => 'required|string|max:100|unique:branches,store_code',
            'is_active' => 'boolean',
        ]);

        $branch = Branch::create($validated);

        return response()->json($branch, 201);
    }

    public function show(Branch $branch)
    {
        return response()->json($branch);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'store_code' => 'sometimes|required|string|max:100|unique:branches,store_code,' . $branch->id,
            'is_active' => 'boolean',
        ]);
        

        $branch->update($validated);

        return response()->json($branch);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }
}