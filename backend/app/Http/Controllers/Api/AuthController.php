<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $request->validate([
            'nipkwt'   => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('nipkwt', $request->nipkwt)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'NIPKWT atau password salah.'
            ], 401);
        }

        if ($user->status === 'nonaktif') {
            return response()->json([
                'message' => 'Akun kamu nonaktif. Hubungi admin.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'nipkwt'   => $user->nipkwt,
                'email'    => $user->email,
                'role'     => $user->role,
                'tim'      => $user->tim,
                'location' => $user->location,
            ]
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }

    // Data user yang sedang login
    public function me(Request $request)
    {
        return response()->json([
            'user' => [
                'id'       => $request->user()->id,
                'name'     => $request->user()->name,
                'username' => $request->user()->username,
                'nipkwt'   => $request->user()->nipkwt,
                'email'    => $request->user()->email,
                'role'     => $request->user()->role,
                'tim'      => $request->user()->tim,
                'location' => $request->user()->location,
            ]
        ]);
    }
}