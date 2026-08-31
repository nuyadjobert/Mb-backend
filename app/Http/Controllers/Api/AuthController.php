<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Store/branch login: crew log in as their store using the branch
     * plus its unique store_code, rather than an individual account.
     */
    public function storeLogin(Request $request)
{
    $validated = $request->validate([
        'branch_id' => 'required|exists:branches,id',
        'store_code' => 'required|string',
        'login_as' => 'required|in:crew,head_crew',
    ]);

    $branch = Branch::findOrFail($validated['branch_id']);

    if (
        ! $branch->is_active
        || ! $branch->store_code
        || ! hash_equals((string) $branch->store_code, (string) $validated['store_code'])
    ) {
        throw ValidationException::withMessages([
            'store_code' => ['The store code is incorrect.'],
        ]);
    }

    // Token carries an "ability" matching the role, so middleware/guards
    // can later check which role this specific session is acting as.
    $token = $branch->createToken('store_token', [$validated['login_as']])->plainTextToken;

    return response()->json([
        'branch' => $branch->makeHidden('store_code'),
        'role' => $validated['login_as'],
        'token' => $token,
    ]);
}

    /**
     * NOTE: In production you'll likely want this behind an admin-only route
     * so random people can't create crew/manager/admin accounts themselves.
     * Left open for now so you can easily create your first test users.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,manager,crew',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('branch'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('branch'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        $authUser = $request->user();

        if ($authUser instanceof Branch) {
            return response()->json($authUser->makeHidden('store_code'));
        }

        return response()->json($authUser->load('branch'));
    }
}