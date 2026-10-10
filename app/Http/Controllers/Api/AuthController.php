<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function login(Request $request)
    {

        $loginData = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = \App\Models\User::where('email', $loginData['email'])->first();

        if(!$user){
            return response(['message' => 'User not found'], 404);
        }

        if(!Hash::check($loginData['password'], $user->password)){
            return response(['message' => 'Invalid password'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response(['message' => 'Login successful', 'token' => $token, 'user' => $user], 200);
    }

    /**
     * Logout: hapus token akses yang sedang dipakai.
     * POST /api/logout (auth:sanctum, header: Authorization: Bearer <token>)
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->currentAccessToken()) {
            return response(['message' => 'Unauthenticated.'], 401);
        }

        $user->currentAccessToken()->delete();

        return response(['message' => 'Logout successful'], 200);
    }

    /**
     * Logout dari semua perangkat: hapus seluruh token milik user.
     * POST /api/logout-all (auth:sanctum)
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response(['message' => 'Unauthenticated.'], 401);
        }

        $user->tokens()->delete();

        return response(['message' => 'Logged out from all devices'], 200);
    }
}
