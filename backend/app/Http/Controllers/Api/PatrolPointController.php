<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatrolPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatrolPointController extends Controller
{
    public function index()
    {
        $patrolPoints = PatrolPoint::orderBy('created_at', 'asc')->get();

        return response()->json([
            'message' => 'Data titik patroli berhasil diambil',
            'patrol_points' => $patrolPoints
        ]);
    }

   public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'location_address' => 'required|string|max:255',
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'radius_meters' => 'required|integer|min:1',
        'description' => 'nullable|string',
        'status' => 'required|in:aktif,nonaktif',
    ], [
        'name.required' => 'Nama titik patroli wajib diisi.',
        'location_address.required' => 'Alamat / lokasi wajib diisi.',
        'latitude.required' => 'Latitude wajib diisi.',
        'latitude.numeric' => 'Latitude harus berupa angka.',
        'longitude.required' => 'Longitude wajib diisi.',
        'longitude.numeric' => 'Longitude harus berupa angka.',
        'radius_meters.required' => 'Radius validasi wajib diisi.',
        'radius_meters.integer' => 'Radius validasi harus berupa angka.',
        'radius_meters.min' => 'Radius validasi minimal 1 meter.',
        'status.required' => 'Status wajib dipilih.',
        'status.in' => 'Status yang dipilih tidak valid.',
    ]);

    // Generate QR Code unik
    $validated['qr_code'] = 'PATROL-' . Str::upper(Str::random(12));

    $patrolPoint = PatrolPoint::create($validated);

    return response()->json([
        'message' => 'Titik patroli berhasil ditambahkan.',
        'patrol_point' => $patrolPoint
    ], 201);
}
    public function show($id)
{
    $patrolPoint = PatrolPoint::find($id);

    if (!$patrolPoint) {
        return response()->json([
            'message' => 'Titik patroli tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'message' => 'Data titik patroli berhasil diambil.',
        'patrol_point' => $patrolPoint
    ]);
}
public function update(Request $request, $id)
    {
        $patrolPoint = PatrolPoint::find($id);

        if (!$patrolPoint) {
            return response()->json([
                'message' => 'Titik patroli tidak ditemukan.'
            ], 404);
        }

       $validated = $request->validate([
    'name' => 'required|string|max:100',
    'location_address' => 'required|string|max:255',
    'latitude' => 'required|numeric',
    'longitude' => 'required|numeric',
    'radius_meters' => 'required|integer|min:1',
    'description' => 'nullable|string',
    'status' => 'required|in:aktif,nonaktif',
], [
    'name.required' => 'Nama titik patroli wajib diisi.',
    'location_address.required' => 'Alamat / lokasi wajib diisi.',
    'latitude.required' => 'Latitude wajib diisi.',
    'latitude.numeric' => 'Latitude harus berupa angka.',
    'longitude.required' => 'Longitude wajib diisi.',
    'longitude.numeric' => 'Longitude harus berupa angka.',
    'radius_meters.required' => 'Radius validasi wajib diisi.',
    'radius_meters.integer' => 'Radius validasi harus berupa angka.',
    'radius_meters.min' => 'Radius validasi minimal 1 meter.',
    'status.required' => 'Status wajib dipilih.',
    'status.in' => 'Status yang dipilih tidak valid.',
]);

        $patrolPoint->update($validated);

        return response()->json([
            'message' => 'Titik patroli berhasil diperbarui.',
            'patrol_point' => $patrolPoint
        ]);
    }
    public function destroy($id)
{
    $patrolPoint = PatrolPoint::find($id);

    if (!$patrolPoint) {
        return response()->json([
            'message' => 'Titik patroli tidak ditemukan.'
        ], 404);
    }

    $patrolPoint->delete();

    return response()->json([
        'message' => 'Titik patroli berhasil dihapus.'
    ]);
}
}