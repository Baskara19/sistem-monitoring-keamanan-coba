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
        $users = User::with('masterLocation')
    ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'username' => $user->username,
                    'nipkwt'   => $user->nipkwt,
                    'email'    => $user->email,
                    'role'     => $user->role,
                    'tim'      => $user->tim,
                    'phone'    => $user->phone,
                    'location' => $user->masterLocation?->name,
                    'location_id' => $user->location_id,
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
            'nipkwt'   => ['required', 'regex:/^[0-9]{6,12}$/', 'unique:users,nipkwt'],
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => 'required|in:satpam,supervisor,admin,katim',
            'tim'      => 'required_if:role,katim|nullable|integer|min:1',
            'phone'    => 'nullable|string|max:20',
            'location_id' => 'nullable|exists:locations,id',
        ], [
            'nipkwt.regex' => 'NIPKWT harus berupa angka, 6-12 digit.',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'nipkwt'   => $request->nipkwt,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'tim'      => $request->role === 'katim' ? $request->tim : null,
            'phone'    => $request->phone,
            'location_id' => $request->location_id,
            'status'   => 'aktif',
        ]);

        // Buat profil sesuai role. Katim dianggap satpam biasa untuk sisi
        // patroli (dashboard, scan, jadwal, dsb sama persis), cuma dibedakan
        // lewat kolom `tim` di atas.
        if (in_array($request->role, ['satpam', 'katim'])) {
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
        'nipkwt'   => ['required', 'regex:/^[0-9]{6,12}$/', 'unique:users,nipkwt,' . $id],
        'email'    => 'required|email|unique:users,email,' . $id,
        'password' => 'nullable|string|min:6',
        'role'     => 'required|in:satpam,supervisor,admin,katim',
        'tim'      => 'required_if:role,katim|nullable|integer|min:1',
        'status'   => 'required|in:aktif,nonaktif',
        'phone'    => 'nullable|string|max:20',
        'location_id' => 'nullable|exists:locations,id',
    ], [
        'nipkwt.regex' => 'NIPKWT harus berupa angka, 6-12 digit.',
    ]);

    // Update data utama user
    $user->name = $request->name;
    $user->username = $request->username;
    $user->nipkwt = $request->nipkwt;
    $user->email = $request->email;
    $user->role = $request->role;
    $user->tim = $request->role === 'katim' ? $request->tim : null;
    $user->status = $request->status;
    $user->phone = $request->phone;
    $user->location_id = $request->location_id;

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
            'nipkwt'   => $user->nipkwt,
            'email'    => $user->email,
            'role'     => $user->role,
            'tim'      => $user->tim,
            'phone'    => $user->phone,
            'location' => $user->masterLocation?->name,
            'location_id' => $user->location_id,
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
       public function archive($id)
{
    $user = User::findOrFail($id);

    $user->update([
        'status' => 'nonaktif',
    ]);

    return response()->json([
        'message' => 'User berhasil dipindahkan ke arsip.',
        'user' => $user,
    ]);
}
public function restore($id)
{
    $user = User::findOrFail($id);

    $user->update([
        'status' => 'aktif',
    ]);

    return response()->json([
        'message' => 'User berhasil diaktifkan kembali.',
        'user' => $user,
    ]);
}
}