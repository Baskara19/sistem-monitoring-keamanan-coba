<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Satpam;
use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // GET /api/admin/users
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'username' => $user->username,
                    'email'    => $user->email,
                    'role'     => $user->role,
                    'phone'    => $user->phone,
                    'location' => $user->location,
                    'status'   => $user->status,
                ];
            });

        return response()->json(['users' => $users]);
    }

    // POST /api/admin/users
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => 'required|in:satpam,supervisor,admin',
            'phone'    => 'nullable|string|max:20',
            'location' => 'nullable|string|max:100',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'phone'    => $request->phone,
            'location' => $request->location,
            'status'   => 'aktif',
        ]);

        // Buat profil sesuai role
        if ($request->role === 'satpam') {
            Satpam::create([
                'user_id'       => $user->id,
                'employee_code' => 'SAT-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'badge_number'  => 'BADGE-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'phone'         => $request->phone,
            ]);
        } elseif ($request->role === 'supervisor') {
            Supervisor::create([
                'user_id'       => $user->id,
                'employee_code' => 'SPV-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'phone'         => $request->phone,
            ]);
        }
        // kalau admin tidak perlu profil tambahan

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user'    => $user
        ], 201);
    }

  // PUT /api/admin/users/{id}
public function update(Request $request, $id)
{
    $user = User::findOrFail($id);

    $request->validate([
        'name'     => 'required|string|max:100',
        'username' => 'required|string|max:50|unique:users,username,' . $id,
        'email'    => 'required|email|unique:users,email,' . $id,
        'password' => 'nullable|string|min:6',
        'role'     => 'required|in:satpam,supervisor,admin',
        'status'   => 'required|in:aktif,nonaktif',
        'phone'    => 'nullable|string|max:20',
        'location' => 'nullable|string|max:100',
    ]);

    // Update data utama user
    $user->name = $request->name;
    $user->username = $request->username;
    $user->email = $request->email;
    $user->role = $request->role;
    $user->status = $request->status;
    $user->phone = $request->phone;
    $user->location = $request->location;

    // Update password hanya jika diisi
    if ($request->filled('password')) {
        $user->password = Hash::make($request->password);
    }

    $user->save();

    return response()->json([
        'message' => 'User berhasil diupdate.',
        'user' => [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'email'    => $user->email,
            'role'     => $user->role,
            'phone'    => $user->phone,
            'location' => $user->location,
            'status'   => $user->status,
        ]
    ]);
}

    // DELETE /api/admin/users/{id}
    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();

        if ($currentUser->id == $id) {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun sendiri.'
            ], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.'
        ]);
    }
}