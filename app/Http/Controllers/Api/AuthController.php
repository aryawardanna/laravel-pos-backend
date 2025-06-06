<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // login api with validation
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // check password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => 'error',
                'message' => 'Password is incorrect'
            ], 401);
        }

        // generate token
        $token = $user->createToken('auth-token')->plainTextToken;
        return response()->json([
            'success' => 'success',
            'message' => 'User logged in successfully',
            'user' => $user,
            'token' => $token
        ], 200);
    }
}
