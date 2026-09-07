<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PatrolLog;
use App\Models\PatrolPoint;
use App\Models\PatrolRoute;
use App\Models\PatrolRoutePoint;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalUsers = User::count();
        $totalPatrolPoints = PatrolPoint::where('status', 'aktif')->count();
        $todayActivities   = PatrolLog::whereDate('scan_time', today())->count();
        $recentActivities  = PatrolLog::with(['satpam.user', 'patrolPoint'])
            ->orderBy('scan_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'satpam_name'        => $log->satpam->user->name ?? '-',
                    'patrol_point_name'  => $log->patrolPoint->name ?? '-',
                    'scan_time'          => $log->scan_time,
                    'scan_status'        => $log->scan_status,
                ];
            });

        return response()->json([
            'total_users'         => $totalUsers,
            'total_patrol_points' => $totalPatrolPoints,
            'today_activities'    => $todayActivities,
            'recent_activities'   => $recentActivities,
        ]);
    }
    public function patrolPoints()
{
    $patrolPoints = PatrolPoint::latest()->get();

    return response()->json([
        'patrol_points' => $patrolPoints
    ]);
}

public function storePatrolPoint(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'location_address' => 'required|string',
        'latitude' => 'nullable|numeric',
        'longitude' => 'nullable|numeric',
        'radius_meters' => 'nullable|integer|min:1',
        'description' => 'nullable|string',
        'status' => 'required|in:aktif,nonaktif',
    ]);

    // Generate QR Code unik
    $validated['qr_code'] = 'PATROL-' . Str::upper(Str::random(12));

    $patrolPoint = PatrolPoint::create($validated);

    return response()->json([
        'message' => 'Titik patroli berhasil ditambahkan.',
        'patrol_point' => $patrolPoint
    ], 201);
}
public function patrolPointQr($id)
{
    $patrolPoint = PatrolPoint::findOrFail($id);

    $qrCode = QrCode::format('svg')
        ->size(300)
        ->margin(2)
        ->generate($patrolPoint->qr_code);

    return response($qrCode)
        ->header('Content-Type', 'image/svg+xml');
}
public function updatePatrolPoint(Request $request, $id)
{
    $patrolPoint = PatrolPoint::findOrFail($id);

    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'location_address' => 'required|string',
        'latitude' => 'nullable|numeric',
        'longitude' => 'nullable|numeric',
        'radius_meters' => 'nullable|integer|min:1',
        'description' => 'nullable|string',
        'status' => 'required|in:aktif,nonaktif',
    ]);

    $patrolPoint->update($validated);

    return response()->json([
        'message' => 'Titik patroli berhasil diperbarui.',
        'patrol_point' => $patrolPoint
    ]);
}

// GET /api/admin/activities
public function activities(Request $request)
{
    $query = PatrolLog::with(['satpam.user', 'patrolPoint', 'report']);

    if ($request->filled('status')) {
        $query->where('scan_status', $request->input('status'));
    }

    if ($request->filled('date')) {
        $query->whereDate('scan_time', $request->input('date'));
    }

    if ($request->filled('search')) {
        $search = $request->input('search');

        $query->where(function ($q) use ($search) {
            $q->whereHas('satpam.user', function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%");
            })->orWhereHas('patrolPoint', function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%");
            });
        });
    }

    $activities = $query->orderBy('scan_time', 'desc')
        ->get()
        ->map(function ($log) {
            return [
                'id'                  => $log->id,
                'scan_time'           => $log->scan_time,
                'satpam_name'         => $log->satpam->user->name ?? '-',
                'patrol_point_name'   => $log->patrolPoint->name ?? '-',
                'scan_status'         => $log->scan_status,
                'distance_from_point' => $log->distance_from_point,
                'note'                => $log->note ?: optional($log->report)->description ?: optional($log->report)->title,
                'report'              => $log->report ? [
                    'report_type' => $log->report->report_type,
                    'title'       => $log->report->title,
                    'description' => $log->report->description,
                    'photo_url'   => $log->report->photo ? asset('storage/' . $log->report->photo) : null,
                ] : null,
            ];
        });

    return response()->json([
        'activities' => $activities,
    ]);
}

private function formatRoute(PatrolRoute $route): array
{
    return [
        'id'          => $route->id,
        'name'        => $route->name,
        'description' => $route->description,
        'status'      => $route->status,
        'points'      => $route->points->map(fn (PatrolRoutePoint $point) => [
            'id'               => $point->id,
            'patrol_point_id'  => $point->patrol_point_id,
            'name'             => $point->patrolPoint?->name ?? '-',
            'location_address' => $point->patrolPoint?->location_address,
            'sequence_order'   => $point->sequence_order,
        ])->values(),
    ];
}

// GET /api/admin/routes
public function routes()
{
    $routes = PatrolRoute::with('points.patrolPoint')
        ->orderBy('name')
        ->get()
        ->map(fn (PatrolRoute $route) => $this->formatRoute($route));

    return response()->json([
        'routes' => $routes,
    ]);
}

// POST /api/admin/routes
public function storeRoute(Request $request)
{
    $validated = $request->validate([
        'name'                 => 'required|string|max:100',
        'description'          => 'nullable|string',
        'status'               => 'required|in:aktif,nonaktif',
        'patrol_point_ids'     => 'required|array|min:1',
        'patrol_point_ids.*'   => 'required|exists:patrol_points,id',
    ]);

    $route = PatrolRoute::create([
        'name'        => $validated['name'],
        'description' => $validated['description'] ?? null,
        'status'      => $validated['status'],
    ]);

    foreach ($validated['patrol_point_ids'] as $index => $patrolPointId) {
        PatrolRoutePoint::create([
            'patrol_route_id' => $route->id,
            'patrol_point_id' => $patrolPointId,
            'sequence_order'  => $index + 1,
        ]);
    }

    $route->load('points.patrolPoint');

    return response()->json([
        'message' => 'Rute patroli berhasil ditambahkan.',
        'route'   => $this->formatRoute($route),
    ], 201);
}

// PUT /api/admin/routes/{id}
public function updateRoute(Request $request, $id)
{
    $route = PatrolRoute::find($id);

    if (! $route) {
        return response()->json(['message' => 'Rute patroli tidak ditemukan.'], 404);
    }

    $validated = $request->validate([
        'name'                 => 'required|string|max:100',
        'description'          => 'nullable|string',
        'status'               => 'required|in:aktif,nonaktif',
        'patrol_point_ids'     => 'required|array|min:1',
        'patrol_point_ids.*'   => 'required|exists:patrol_points,id',
    ]);

    $route->update([
        'name'        => $validated['name'],
        'description' => $validated['description'] ?? null,
        'status'      => $validated['status'],
    ]);

    $route->points()->delete();

    foreach ($validated['patrol_point_ids'] as $index => $patrolPointId) {
        PatrolRoutePoint::create([
            'patrol_route_id' => $route->id,
            'patrol_point_id' => $patrolPointId,
            'sequence_order'  => $index + 1,
        ]);
    }

    $route->load('points.patrolPoint');

    return response()->json([
        'message' => 'Rute patroli berhasil diperbarui.',
        'route'   => $this->formatRoute($route),
    ]);
}

// DELETE /api/admin/routes/{id}
public function destroyRoute($id)
{
    $route = PatrolRoute::find($id);

    if (! $route) {
        return response()->json(['message' => 'Rute patroli tidak ditemukan.'], 404);
    }

    $route->delete();

    return response()->json([
        'message' => 'Rute patroli berhasil dihapus.',
    ]);
}
}