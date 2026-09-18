<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::orderBy('name')->get();

        return response()->json($locations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:locations,name',
            ],
            'status' => [
                'nullable',
                Rule::in(['aktif', 'nonaktif']),
            ],
        ]);

        $location = Location::create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'aktif',
        ]);

        return response()->json([
            'message' => 'Lokasi berhasil ditambahkan.',
            'data' => $location,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('locations', 'name')->ignore($location->id),
            ],
            'status' => [
                'required',
                Rule::in(['aktif', 'nonaktif']),
            ],
        ]);

        $location->update($validated);

        return response()->json([
            'message' => 'Lokasi berhasil diperbarui.',
            'data' => $location,
        ]);
    }

    public function destroy($id)
    {
        $location = Location::findOrFail($id);

        if ($location->users()->exists()) {
            return response()->json([
                'message' => 'Lokasi tidak dapat dihapus karena masih digunakan oleh user.',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Lokasi berhasil dihapus.',
        ]);
    }
}