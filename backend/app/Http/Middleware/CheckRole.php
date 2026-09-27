<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk memverifikasi role user yang sedang login.
 * Dipasang di route group agar setiap endpoint terlindungi
 * secara otomatis tanpa perlu cek manual di tiap controller.
 *
 * Penggunaan di routes/api.php:
 *   Route::prefix('admin')->middleware('role:admin')->group(...)
 *   Route::prefix('satpam')->middleware('role:satpam,katim')->group(...)
 *   Route::prefix('supervisor')->middleware('role:supervisor')->group(...)
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Pastikan user sudah ter-autentikasi dan role-nya termasuk
        // dalam daftar role yang diizinkan untuk route ini.
        if (! $user || ! in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengakses endpoint ini.',
            ], 403);
        }

        return $next($request);
    }
}
