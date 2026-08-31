<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        return response()->json(Item::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'divisor' => 'nullable|numeric|min:0.01',
            'is_active' => 'boolean',
        ]);

        $item = Item::create($validated);

        return response()->json($item, 201);
    }

    public function show(Item $item)
    {
        return response()->json($item);
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'price' => 'sometimes|required|numeric|min:0',
            'divisor' => 'nullable|numeric|min:0.01',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return response()->json(['message' => 'Item deleted.']);
    }
}