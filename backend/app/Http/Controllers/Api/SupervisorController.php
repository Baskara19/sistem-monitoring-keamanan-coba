<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatrolLog;
use App\Models\PatrolPoint;
use App\Models\PatrolRoute;
use App\Models\PatrolRoutePoint;
use App\Models\Report;
use App\Models\Satpam;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\Supervisor;
use App\Models\SkipReason;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SupervisorController extends Controller
{
    /**
     * Data ringkas untuk halaman monitoring supervisor.
     */
    public function monitoring(Request $request)
    {
        $today = today();
           $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }

        $patrolPoints = PatrolPoint::query()
            ->orderBy('name')
            ->get()
            ->map(fn (PatrolPoint $point) => [
                'id' => $point->id,
                'name' => $point->name,
                'location' => $point->location ?? $point->location_address,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'status' => $point->status,
            ])
            ->values();

        $activeDetails = ScheduleDetail::query()
            ->with(['satpam.user', 'patrolPoint'])
                ->whereHas('satpam.user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    })
            ->whereHas('schedule', function ($query) use ($today) {
                $query->where('status', 'aktif')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today);
            })
            ->whereHas('satpam', fn ($query) => $query->where('status', 'aktif'))
            ->orderBy('sequence_order')
            ->get();

        $todayLogs = PatrolLog::query()
            ->with('patrolPoint:id,name')
            ->whereHas('satpam.user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    })
            ->whereDate('scan_time', $today)
            ->orderByDesc('scan_time')
            ->get();

        $latestLogsBySatpam = $todayLogs->groupBy('satpam_id')
            ->map(fn ($logs) => $logs->first());

        $activeSatpams = $activeDetails->groupBy('satpam_id')
            ->map(function ($details, $satpamId) use ($latestLogsBySatpam) {
                $detail = $details->first();
                $latestLog = $latestLogsBySatpam->get($satpamId);

                return [
                    'id' => $satpamId,
                    'name' => $detail->satpam?->user?->name ?? '-',
                    'shift' => $detail->shift_label . ' ('
                        . substr($detail->shift_start, 0, 5) . '-'
                        . substr($detail->shift_end, 0, 5) . ')',
                    'current_point' => $latestLog?->patrolPoint?->name
                        ?? $detail->patrolPoint?->name,
                    'status' => $latestLog?->scan_status ?? 'aktif',
                    'last_scan' => $latestLog?->scan_time,
                ];
            })
            ->values();

        return response()->json([
            'date' => $today->toDateString(),
            'statistics' => [
                'berjalan' => $activeSatpams->count(),
                'selesai' => $todayLogs->where('scan_status', 'berhasil')->count(),
                'terlambat' => $todayLogs->where('scan_status', 'terlambat')->count(),
                'anomali' => $todayLogs->where('scan_status', 'anomali')->count(),
                'skip' => $todayLogs->where('scan_status', 'skip')->count(),
                'terlewat' => $todayLogs->where('scan_status', 'terlewat')->count(),
                'offline' => Satpam::where('status', 'aktif') ->whereHas('user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    })->count() - $activeSatpams->count(),
            ],
            'patrol_points' => $patrolPoints,
            'active_satpams' => $activeSatpams,
        ]);
    }

    /**
     * Dashboard Supervisor
     */
    public function dashboard(Request $request)
    {
        // =====================================================
        // TANGGAL HARI INI
        // =====================================================

        $today = today();
 $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }
        // =====================================================
        // DATA PATROLI HARI INI
        // =====================================================

        $patrolLogs = PatrolLog::with([
            'satpam.user',
            'patrolPoint',
            'scheduleDetail.patrolPoint',
            'report',
            'skipReason',
        ])
         ->whereHas('satpam.user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    })
            ->whereDate('scan_time', $today)
            ->orderBy('scan_time', 'desc')
            ->get();

        // =====================================================
        // STATISTIK PATROLI
        // =====================================================

        $totalPatroli = $patrolLogs->count();

        $selesai = $patrolLogs
            ->where('scan_status', 'berhasil')
            ->count();

        $terlambat = $patrolLogs
            ->where('scan_status', 'terlambat')
            ->count();

        $skip = $patrolLogs
            ->where('scan_status', 'skip')
            ->count();

        $anomali = $patrolLogs
            ->where('scan_status', 'anomali')
            ->count();

        // =====================================================
        // JUMLAH SATPAM AKTIF
        // =====================================================

        $totalSatpam = $patrolLogs
            ->pluck('satpam_id')
            ->filter()
            ->unique()
            ->count();

        // =====================================================
        // DATA LAPORAN
        // =====================================================

        /*
         * Karena setiap scan wajib membuat laporan,
         * laporan dihitung berdasarkan patrol log hari ini
         * yang sudah memiliki relasi report.
         */

        $totalReports = $patrolLogs
            ->filter(function ($log) {
                return $log->report !== null;
            })
            ->count();

        /*
         * Patrol yang belum mempunyai laporan.
         *
         * Ini berguna untuk monitoring supervisor.
         */

        $pendingReports = $patrolLogs
            ->filter(function ($log) {
                return $log->report === null;
            })
            ->count();

        // =====================================================
        // PROGRESS PATROLI
        // =====================================================

        $completedPatrols = $selesai;

        $skipPatrols = $skip;

        $latePatrols = $terlambat;

        $missedPatrols = $patrolLogs
            ->where('scan_status', 'terlewat')
            ->count();

        $progressPercentage = $totalPatroli > 0
            ? round(($completedPatrols / $totalPatroli) * 100)
            : 0;

        // =====================================================
        // MONITORING SATPAM
        // =====================================================

        $guards = $patrolLogs
            ->groupBy('satpam_id')
            ->map(function ($logs) {

                $lastLog = $logs
                    ->sortByDesc('scan_time')
                    ->first();

                $total = $logs->count();

                $completed = $logs
                    ->where('scan_status', 'berhasil')
                    ->count();

                $progress = $total > 0
                    ? round(($completed / $total) * 100)
                    : 0;

                return [
                    'id' => $lastLog->satpam_id,

                    'name' => $lastLog->satpam?->user?->name ?? '-',

                    'badge' => $lastLog->satpam?->badge_number ?? '-',

                    'shift' => $lastLog->scheduleDetail
                        ? $lastLog->scheduleDetail->shift_label
                            . ' (' . substr($lastLog->scheduleDetail->shift_start, 0, 5)
                            . '-' . substr($lastLog->scheduleDetail->shift_end, 0, 5) . ')'
                        : '-',

                    'route' => $lastLog->scheduleDetail?->patrolPoint?->name
                        ?? $lastLog->patrolPoint?->name
                        ?? '-',

                    'progress' => $progress,

                    'last_scan' => $lastLog->scan_time,

                    'status' => $lastLog->scan_status,
                ];
            })
            ->values();

        // =====================================================
        // AKTIVITAS TERBARU
        // =====================================================

        $activities = $patrolLogs
            ->take(10)
            ->map(function ($log) {

                return [
                    'id' => $log->id,

                    'satpam_name' => $log->satpam?->user?->name ?? '-',

                    'badge' => $log->satpam?->badge_number ?? '-',

                    'patrol_point_name' => $log->patrolPoint?->name ?? '-',

                    'scan_time' => $log->scan_time,

                    'scan_status' => $log->scan_status,

                    'latitude' => $log->latitude,

                    'longitude' => $log->longitude,

                    'distance_from_point' => $log->distance_from_point,

                    'note' => $log->note,

                    'has_report' => $log->report !== null,

                    'skip_reason' => $log->skipReason?->reason,
                ];
            })
            ->values();

        // =====================================================
        // RESPONSE
        // =====================================================

        return response()->json([
            'message' => 'Data dashboard supervisor berhasil diambil',

            'date' => $today,

            // =================================================
            // STATISTICS
            // =================================================

            'statistics' => [
                'total_satpam' => $totalSatpam,
                'total_patroli' => $totalPatroli,
                'selesai' => $selesai,
                'terlambat' => $terlambat,
                'skip' => $skip,
                'anomali' => $anomali,
            ],

            // =================================================
            // REPORT STATISTICS
            // =================================================

            'stats' => [
                'total_reports' => $totalReports,
                'pending_reports' => $pendingReports,

                'completed_patrols' => $completedPatrols,
                'skip_patrols' => $skipPatrols,
                'late_patrols' => $latePatrols,
                'missed_patrols' => $missedPatrols,
            ],

            // =================================================
            // PROGRESS
            // =================================================

            'progress' => [
                'percentage' => $progressPercentage,
                'completed' => $completedPatrols,
    
                  'skip' => $skipPatrols,
                'late' => $latePatrols,
                'missed' => $missedPatrols,
            ],

            // =================================================
            // SATPAM
            // =================================================

            'guards' => $guards,

            // =================================================
            // AKTIVITAS
            // =================================================

            'activities' => $activities,
        ]);
    }

    /**
     * Recap scan patroli per bulan, dengan opsi fokus ke satu satpam.
     * GET /api/supervisor/recap?bulan=9&tahun=2026&satpam_id=12
     */
    public function recap(Request $request)
    {
        $locationId = $this->supervisorLocationId($request);

        if (! $locationId) {
            return response()->json(['message' => 'Lokasi supervisor belum ditentukan.'], 403);
        }

        $request->validate([
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tahun' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'satpam_id' => ['nullable', 'integer'],
        ]);

        $month = (int) ($request->input('bulan') ?: now()->month);
        $year = (int) ($request->input('tahun') ?: now()->year);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $satpams = Satpam::with('user:id,name,location_id')
            ->whereHas('user', fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('id')
            ->get();

        $logs = PatrolLog::query()
            ->whereHas('satpam.user', fn ($query) => $query->where('location_id', $locationId))
            ->whereBetween('scan_time', [$start, $end])
            ->get(['satpam_id', 'scan_status', 'scan_time']);

        // Hitung total schedule_details yang dijadwalkan per satpam di bulan ini.
        // "scheduled" = jumlah baris jadwal (titik patroli) yang ditetapkan ke satpam
        // pada jadwal yang periode-nya overlap dengan bulan yang dipilih.
        // Nilai ini menjadi "target" pada bar chart PDF per satpam.
        $scheduledCounts = ScheduleDetail::query()
            ->whereIn('satpam_id', $satpams->pluck('id'))
            ->whereHas('schedule', function ($q) use ($start, $end) {
                $q->where('status', 'aktif')
                  ->whereDate('start_date', '<=', $end)
                  ->whereDate('end_date', '>=', $start);
            })
            ->selectRaw('satpam_id, COUNT(*) as total_scheduled')
            ->groupBy('satpam_id')
            ->pluck('total_scheduled', 'satpam_id');

        $countsFor = function ($satpamLogs) {
            return [
                'berhasil' => $satpamLogs->where('scan_status', 'berhasil')->count(),
                'terlambat' => $satpamLogs->where('scan_status', 'terlambat')->count(),
                'anomali' => $satpamLogs->where('scan_status', 'anomali')->count(),
                'skip' => $satpamLogs->where('scan_status', 'skip')->count(),
                'terlewat' => $satpamLogs->where('scan_status', 'terlewat')->count(),
                'lainnya' => $satpamLogs->whereNotIn('scan_status', ['berhasil', 'terlambat', 'anomali', 'skip', 'terlewat'])->count(),
                'total' => $satpamLogs->count(),
            ];
        };

        $bySatpam = $satpams->map(function (Satpam $satpam) use ($logs, $countsFor, $scheduledCounts) {
            $counts = $countsFor($logs->where('satpam_id', $satpam->id));

            return [
                'id'        => $satpam->id,
                'name'      => $satpam->user?->name ?? '-',
                'scheduled' => (int) ($scheduledCounts[$satpam->id] ?? 0),
                ...$counts,
            ];
        })->values();

        $selectedId = $request->filled('satpam_id') ? (int) $request->input('satpam_id') : null;
        $selected = $selectedId ? $bySatpam->firstWhere('id', $selectedId) : null;

        $monthly = $countsFor($logs);
        $leader = fn ($key) => $bySatpam
            ->sortByDesc($key)
            ->first(fn ($row) => $row[$key] > 0) ?: null;

        return response()->json([
            'period' => [
                'bulan' => $month,
                'tahun' => $year,
                'label' => $start->translatedFormat('F Y'),
            ],
            'monthly' => $monthly,
            'leaders' => [
                'berhasil' => $leader('berhasil'),
                'terlambat' => $leader('terlambat'),
                'anomali' => $leader('anomali'),
                'skip' => $leader('skip'),
                'terlewat' => $leader('terlewat'),
            ],
            'satpams' => $bySatpam,
            'selected' => $selected,
        ]);
    }

    /**
     * Ambil data Supervisor dari user yang sedang login.
     */
    private function currentSupervisor(Request $request)
    {
        return Supervisor::where('user_id', $request->user()->id)->first();
    }

    private function supervisorLocationId(Request $request)
{
    return $request->user()->location_id;
}

    private function formatScheduleRow(ScheduleDetail $detail): array
    {
        return [
            'id'                => $detail->id,
            'schedule_id'       => $detail->schedule_id,
            'satpam_id'         => $detail->satpam_id,
            'satpam_name'       => $detail->satpam?->user?->name ?? '-',
            'patrol_point_id'   => $detail->patrol_point_id,
            'area'              => $detail->patrolPoint?->name ?? '-',
            'start_date'        => $detail->schedule?->start_date,
            'end_date'          => $detail->schedule?->end_date,
            'shift_start'       => $detail->shift_start,
            'shift_end'         => $detail->shift_end,
            'shift_label'       => $detail->shift_label,
            'sequence_order'    => $detail->sequence_order,
            'status'            => $detail->schedule?->status ?? 'nonaktif',
        ];
    }

    // GET /api/supervisor/schedules
    // GET /api/supervisor/schedules
public function scheduleIndex(Request $request)
{
    $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }

    $query = ScheduleDetail::with([
        'schedule',
        'satpam.user',
        'patrolPoint',
    ])->whereHas('satpam.user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    });

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

    if ($request->filled('date_from') || $request->filled('date_to')) {
        $query->whereHas('schedule', function ($q) use ($request) {
            if ($request->filled('date_from')) {
                $q->whereDate('end_date', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $q->whereDate('start_date', '<=', $request->input('date_to'));
            }
        });
    }

    $rows = $query->join(
        'schedules',
        'schedules.id',
        '=',
        'schedule_details.schedule_id'
    )
        ->orderBy('schedules.start_date', 'desc')
        ->select('schedule_details.*')
        ->get()
        ->map(fn (ScheduleDetail $detail) => $this->formatScheduleRow($detail));

    return response()->json([
        'schedules' => $rows,
    ]);
}

    // POST /api/supervisor/schedules
    public function scheduleStore(Request $request)
    {
        $validated = $request->validate([
            'satpam_id'   => 'required|exists:satpams,id',
            'route_id'    => 'required|exists:patrol_routes,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'shift_start' => 'required',
            'shift_end'   => 'required',
            'status'      => 'nullable|in:aktif,nonaktif',
        ]);

        $supervisor = $this->currentSupervisor($request);

        if (! $supervisor) {
            return response()->json([
                'message' => 'Data supervisor tidak ditemukan untuk akun ini.',
            ], 404);
        }
        $locationId = $this->supervisorLocationId($request);

if (! $locationId) {
    return response()->json([
        'message' => 'Lokasi supervisor belum ditentukan.',
    ], 403);
}

$satpam = Satpam::with('user')
    ->where('id', $validated['satpam_id'])
    ->whereHas('user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    })
    ->first();

if (! $satpam) {
    return response()->json([
        'message' => 'Satpam tidak berada di lokasi supervisor.',
    ], 403);
}

        $route = PatrolRoute::with('points')->find($validated['route_id']);

        if (! $route || $route->points->isEmpty()) {
            return response()->json([
                'message' => 'Rute patroli tidak ditemukan atau belum punya titik.',
            ], 404);
        }

       $satpamName = $satpam->user?->name ?? 'Satpam';

        $schedule = Schedule::create([
            'supervisor_id' => $supervisor->id,
            'title'         => "Jadwal {$satpamName} - {$route->name}",
            'description'   => null,
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'status'        => $validated['status'] ?? 'aktif',
        ]);

        // Urutan titik patroli (1, 2, 3, ...) dihitung otomatis dari jumlah
        // titik yang sudah dijadwalkan untuk satpam yang sama di tanggal mulai
        // yang sama, lalu dilanjut sesuai urutan titik di dalam rute yang dipilih.
        $startingSequence = ScheduleDetail::where('satpam_id', $validated['satpam_id'])
            ->whereHas('schedule', function ($query) use ($validated) {
                $query->where('start_date', $validated['start_date']);
            })
            ->count();

        $details = collect();

        foreach ($route->points as $index => $routePoint) {
            $detail = ScheduleDetail::create([
                'schedule_id'      => $schedule->id,
                'satpam_id'        => $validated['satpam_id'],
                'patrol_point_id'  => $routePoint->patrol_point_id,
                'shift_start'      => $validated['shift_start'],
                'shift_end'        => $validated['shift_end'],
                'sequence_order'   => $startingSequence + $index + 1,
            ]);

            $detail->load(['schedule', 'satpam.user', 'patrolPoint']);
            $details->push($detail);
        }

        return response()->json([
            'message'   => 'Jadwal berhasil ditambahkan.',
            'schedules' => $details->map(fn (ScheduleDetail $detail) => $this->formatScheduleRow($detail))->values(),
        ], 201);
    }

    // PUT /api/supervisor/schedules/{id}
    public function scheduleUpdate(Request $request, $id)
    {
        $detail = ScheduleDetail::with('schedule')->find($id);

        if (! $detail) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan.',
            ], 404);
        }
   $supervisor = $this->currentSupervisor($request);

   $locationId = $this->supervisorLocationId($request);

if (! $locationId) {
    return response()->json([
        'message' => 'Lokasi supervisor belum ditentukan.',
    ], 403);
}
        $validated = $request->validate([
            'satpam_id'       => 'required|exists:satpams,id',
            'patrol_point_id' => 'required|exists:patrol_points,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'shift_start'     => 'required',
            'shift_end'       => 'required',
            'status'          => 'required|in:aktif,nonaktif',
        ]);

$satpam = Satpam::with('user')
    ->where('id', $validated['satpam_id'])
    ->whereHas('user', function ($query) use ($locationId) {
        $query->where('location_id', $locationId);
    })
    ->first();

if (! $satpam) {
    return response()->json([
        'message' => 'Satpam tidak berada di lokasi supervisor.',
    ], 403);
}

        $detail->update([
            'satpam_id'       => $validated['satpam_id'],
            'patrol_point_id' => $validated['patrol_point_id'],
            'shift_start'     => $validated['shift_start'],
            'shift_end'       => $validated['shift_end'],
        ]);

        $detail->schedule?->update([
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'status'     => $validated['status'],
        ]);

        $detail->load(['schedule', 'satpam.user', 'patrolPoint']);

        return response()->json([
            'message'  => 'Jadwal berhasil diperbarui.',
            'schedule' => $this->formatScheduleRow($detail),
        ]);
    }

  // DELETE /api/supervisor/schedules/{id}
public function scheduleDestroy(Request $request, $id)
{
    $detail = ScheduleDetail::with('schedule')->find($id);

    if (! $detail) {
        return response()->json([
            'message' => 'Jadwal tidak ditemukan.',
        ], 404);
    }

    $supervisor = $this->currentSupervisor($request);

    if (! $supervisor) {
        return response()->json([
            'message' => 'Data supervisor tidak ditemukan untuk akun ini.',
        ], 404);
    }

    $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }

    // Pastikan jadwal ini memang dibuat oleh supervisor yang sedang login.
    if ($detail->schedule?->supervisor_id !== $supervisor->id) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses untuk menghapus jadwal ini.',
        ], 403);
    }

    // Pastikan Satpam pada jadwal berada di lokasi supervisor.
    $belongsToLocation = Satpam::where('id', $detail->satpam_id)
        ->whereHas('user', function ($query) use ($locationId) {
            $query->where('location_id', $locationId);
        })
        ->exists();

    if (! $belongsToLocation) {
        return response()->json([
            'message' => 'Jadwal ini bukan milik Satpam di lokasi supervisor.',
        ], 403);
    }

    $scheduleId = $detail->schedule_id;

    $detail->delete();

    if (ScheduleDetail::where('schedule_id', $scheduleId)->doesntExist()) {
        Schedule::where('id', $scheduleId)->delete();
    }

    return response()->json([
        'message' => 'Jadwal berhasil dihapus.',
    ]);
}

    // GET /api/supervisor/satpam
   public function satpamList(Request $request)
{
    $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }

    $satpam = Satpam::with('user:id,name,location_id')
        ->where('status', 'aktif')
        ->whereHas('user', function ($query) use ($locationId) {
            $query->where('location_id', $locationId);
        })
        ->get()
        ->map(fn (Satpam $s) => [
            'id'   => $s->id,
            'name' => $s->user?->name ?? '-',
        ]);

    return response()->json([
        'satpam' => $satpam,
    ]);
}

    // GET /api/supervisor/patrol-points
    public function patrolPointList()
    {
        $patrolPoints = PatrolPoint::where('status', 'aktif')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'patrol_points' => $patrolPoints,
        ]);
    }

    // GET /api/supervisor/routes
    public function routeList()
    {
        $routes = PatrolRoute::with('points.patrolPoint')
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get()
            ->map(fn (PatrolRoute $route) => [
                'id'     => $route->id,
                'name'   => $route->name,
                'points' => $route->points->map(fn (PatrolRoutePoint $point) => [
                    'patrol_point_id' => $point->patrol_point_id,
                    'name'            => $point->patrolPoint?->name ?? '-',
                    'sequence_order'  => $point->sequence_order,
                ])->values(),
            ]);

        return response()->json([
            'routes' => $routes,
        ]);
    }
// GET /api/supervisor/reports
public function reports(Request $request)
{
        $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }
    $query = PatrolLog::with([
        'satpam.user',
        'patrolPoint',
        'scheduleDetail.schedule',
        'scheduleDetail.patrolPoint',
        'report',
        'skipReason',
    ]);
 $query->whereHas('satpam.user', function ($q) use ($locationId) {
        $q->where('location_id', $locationId);
    });
    // Filter tanggal
    if ($request->filled('date')) {
        $query->whereDate('scan_time', $request->date);
    }

    // Filter status
    if ($request->filled('status')) {
        $query->where('scan_status', $request->status);
    }

    // Filter nama satpam
    if ($request->filled('satpam')) {
        $search = $request->satpam;

        $query->whereHas('satpam.user', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });
    }

    $logs = $query
        ->orderBy('scan_time', 'desc')
        ->get();

    $reports = $logs->map(function ($log) {

        /*
         * Schedule detail tempat laporan/scan ini berasal.
         */
        $currentDetail = $log->scheduleDetail;

        /*
         * Ambil seluruh titik dalam schedule yang sama.
         *
         * Karena 1 schedule dibuat dari 1 route,
         * schedule_details sudah berisi seluruh titik
         * beserta sequence_order-nya.
         */
        $scheduleDetails = collect();

        if ($currentDetail?->schedule_id) {
            $scheduleDetails = ScheduleDetail::with([
                'patrolPoint',
            ])
                ->where('schedule_id', $currentDetail->schedule_id)
                ->orderBy('sequence_order')
                ->get();
        }

        /*
         * Ambil seluruh patrol log dari schedule yang sama.
         */
        $scheduleLogs = collect();

        if ($currentDetail?->schedule_id) {
            $scheduleLogs = PatrolLog::whereHas('scheduleDetail', function ($query) use ($currentDetail) {
                $query->where('schedule_id', $currentDetail->schedule_id);
            })
                ->orderBy('scan_time')
                ->get()
                ->groupBy('schedule_detail_id');
        }

        /*
         * Bentuk timeline berdasarkan urutan schedule detail.
         */
        $patrolTimeline = $scheduleDetails
            ->map(function ($detail) use ($scheduleLogs) {

                $pointLogs = $scheduleLogs->get($detail->id, collect());

                /*
                 * Ambil scan pertama untuk titik tersebut.
                 */
                $pointLog = $pointLogs->first();

                return [
                    'schedule_detail_id' => $detail->id,

                    'sequence_order' => $detail->sequence_order,

                    'patrol_point_id' => $detail->patrol_point_id,

                    'patrol_point_name' => $detail->patrolPoint?->name ?? '-',

                    'scan_time' => $pointLog?->scan_time,

                    'status' => $pointLog?->scan_status ?? 'belum',

                    'note' => $pointLog?->note,
                ];
            })
            ->values();

        return [
            'id' => $log->id,

            'satpam_name' => $log->satpam?->user?->name ?? '-',

            'nipkwt' => $log->satpam?->user?->nipkwt ?? '-',

            'patrol_point' => $log->patrolPoint?->name ?? '-',

            'scan_time' => $log->scan_time,

            'scan_status' => $log->scan_status ?? '-',

            'note' => $log->note,

            'report_id' => $log->report?->id,

            'report_title' => $log->report?->title,

            'report_description' => $log->report?->description,

            'report_photo' => $log->report?->photo,
            'review_status' => $log->report?->review_status,

            // SKIP
'skip_reason' => $log->skipReason?->reason,
'skip_reason_id' => $log->skipReason?->id,
'skip_review_status' => $log->skipReason?->review_status,

'schedule_id' => $currentDetail?->schedule_id,
'patrol_timeline' => $patrolTimeline,

            /*
             * Data yang dipakai Timeline Detail Laporan.
             */
            'schedule_id' => $currentDetail?->schedule_id,

            'patrol_timeline' => $patrolTimeline,
        ];
    })->values();

    // Statistik
    $todayLogs = PatrolLog::with([
        'report',
         'skipReason',
    ])
     ->whereHas('satpam.user', function ($q) use ($locationId) {
        $q->where('location_id', $locationId);
    })
        ->whereDate('scan_time', today())
        ->get();

    $totalReports = $todayLogs
        ->filter(fn ($log) => $log->report !== null)
        ->count();

   $pendingReports = $todayLogs
    ->filter(function ($log) {
        $reportPending = $log->report !== null
            && $log->report->review_status === 'pending';

        $skipPending = $log->skipReason !== null
            && $log->skipReason->review_status === 'pending';

        return $reportPending || $skipPending;
    })
    ->count();

    $skipReports = $todayLogs
        ->where('scan_status', 'skip')
        ->count();

    $anomalyReports = $todayLogs
        ->where('scan_status', 'anomali')
        ->count();

    $totalSatpam = Satpam::query()
        ->whereHas('user', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        })
        ->count();

    return response()->json([
        'message' => 'Data laporan berhasil diambil',

        'statistics' => [
            'total_reports' => $totalReports,
            'pending_reports' => $pendingReports,
            'total_satpam' => $totalSatpam,
        ],

        'total' => $reports->count(),

        'reports' => $reports,
    ]);
}
/**
 * PUT /api/supervisor/reports/{id}/review
 *
 * Menandai laporan sebagai sudah ditinjau supervisor.
 */
public function reviewReport(Request $request, $id)
{
    $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }

    $report = Report::with('patrolLog.satpam.user')
        ->find($id);

    if (! $report) {
        return response()->json([
            'message' => 'Laporan tidak ditemukan.',
        ], 404);
    }

    $reportLocationId = $report->patrolLog?->satpam?->user?->location_id;

    if ((int) $reportLocationId !== (int) $locationId) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses untuk meninjau laporan dari lokasi lain.',
        ], 403);
    }

    $report->update([
        'review_status' => 'reviewed',
        'reviewed_at'   => now(),
        'reviewed_by'   => $request->user()->id,
    ]);

    return response()->json([
        'message' => 'Laporan berhasil ditandai sebagai sudah ditinjau.',
        'report' => [
            'id' => $report->id,
            'review_status' => $report->review_status,
        ],
    ]);
}
/**
 * PUT /api/supervisor/skips/{id}/review
 *
 * Menandai Skip Scan sebagai sudah ditinjau supervisor.
 */
public function reviewSkip(Request $request, $id)
{
    $locationId = $this->supervisorLocationId($request);

    if (! $locationId) {
        return response()->json([
            'message' => 'Lokasi supervisor belum ditentukan.',
        ], 403);
    }

    $skipReason = SkipReason::with('patrolLog.satpam.user')
        ->find($id);

    if (! $skipReason) {
        return response()->json([
            'message' => 'Data skip tidak ditemukan.',
        ], 404);
    }

    $skipLocationId = $skipReason->patrolLog?->satpam?->user?->location_id;

    if ((int) $skipLocationId !== (int) $locationId) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses untuk meninjau skip dari lokasi lain.',
        ], 403);
    }

    $skipReason->update([
        'review_status' => 'reviewed',
    ]);

    return response()->json([
        'message' => 'Skip berhasil ditandai sebagai sudah ditinjau.',
        'skip' => [
            'id' => $skipReason->id,
            'review_status' => $skipReason->review_status,
        ],
    ]);
}

    // Kolom tetap (di luar kolom tanggal 1-31) yang wajib ada di file import.
    private const JADWAL_HEADER_COLUMNS = ['no', 'nama', 'jabatan', 'nipkwt', 'rute'];

    // Jam shift tetap sesuai kode P/S/M. Malam (M) sengaja lintas tengah
    // malam (22:00 -> 06:00 keesokan harinya) — ditangani oleh logic overnight
    // di SatpamController saat satpam scan/lihat shift aktifnya.
    private const SHIFT_TIMES = [
        'P' => ['06:00', '14:00'],
        'S' => ['14:00', '22:00'],
        'M' => ['22:00', '06:00'],
    ];

    // GET /api/supervisor/schedules-import-template
    public function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal');

        $totalCols = 5 + 31; // No, Nama, Jabatan, NIPKWT, Rute + tanggal 1-31
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);

        $navy = '1F2454';
        $orange = 'E87500';
        $grayText = '6B6F80';
        $borderColor = 'D9DCE8';

        // ===== JUDUL =====
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'TEMPLATE IMPORT JADWAL DINAS SATPAM');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Isi data di bawah ini, lalu upload lewat menu Import Jadwal. Bulan & tahun dipilih terpisah saat upload.');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => $grayText]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ===== PITA "TANGGAL" DI ATAS KOLOM-KOLOM TANGGAL =====
        $tanggalRow = 3;
        $sheet->mergeCells("A{$tanggalRow}:E{$tanggalRow}");
        $sheet->mergeCells("F{$tanggalRow}:{$lastCol}{$tanggalRow}");
        $sheet->setCellValue("F{$tanggalRow}", 'TANGGAL');
        $sheet->getStyle("A{$tanggalRow}:{$lastCol}{$tanggalRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($tanggalRow)->setRowHeight(20);

        // ===== HEADER TABEL =====
        $headerRow = 4;
        $header = ['No', 'Nama', 'Jabatan', 'NIPKWT', 'Rute'];

        for ($day = 1; $day <= 31; $day++) {
            $header[] = (string) $day;
        }

        $sheet->fromArray($header, null, "A{$headerRow}");

        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // ===== BARIS CONTOH + BEBERAPA BARIS KOSONG BERGARIS =====
        $exampleRow = $headerRow + 1;
        $example = array_merge(
            [1, 'Contoh Nama', 'PAM', '123456', 'Rute A'],
            ['P', 'P', 'L', 'S', 'S', 'M', 'M', 'L'],
        );

        $sheet->fromArray($example, null, "A{$exampleRow}");

        $blankRowsAfterExample = 9;
        $lastDataRow = $exampleRow + $blankRowsAfterExample;

        $sheet->getStyle("A{$exampleRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("B{$exampleRow}:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // ===== LEBAR KOLOM =====
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);

        foreach (range(6, $totalCols) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setWidth(4);
        }

        // ===== LEGENDA =====
        $legendRow = $lastDataRow + 2;
        $sheet->mergeCells("A{$legendRow}:{$lastCol}{$legendRow}");
        $sheet->setCellValue("A{$legendRow}", 'KETERANGAN KODE SHIFT');
        $sheet->getStyle("A{$legendRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $orange]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Setiap item legenda sengaja digabung jadi SATU sel yang merge
        // selebar tabel (bukan kolom terpisah) — supaya gak ketabrak posisi
        // kolom Nama/NIPKWT dan bikin parser import salah baca baris ini
        // sebagai baris data.
        $legendItems = [
            'P = Pagi (06:00 - 14:00)',
            'S = Siang (14:00 - 22:00)',
            'M = Malam (22:00 - 06:00, lanjut ke hari berikutnya)',
            'L = Libur (tidak ada jadwal hari itu) — kolom kosong juga dianggap Libur',
        ];

        foreach ($legendItems as $offset => $text) {
            $r = $legendRow + 1 + $offset;
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->setCellValue("A{$r}", $text);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $navy]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
            ]);
        }

        // ===== CATATAN TAMBAHAN =====
        $noteRow = $legendRow + count($legendItems) + 2;
        $notes = [
            'Jabatan diisi "PAM" untuk satpam biasa, atau "KATIM 1" / "KATIM 2" dst untuk katim.',
            'NIPKWT & Rute wajib diisi persis sesuai data yang sudah terdaftar di sistem.',
            'Bulan & tahun jadwal dipilih terpisah saat proses import, bukan dari file ini.',
        ];

        foreach ($notes as $offset => $note) {
            $r = $noteRow + $offset;
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            $sheet->setCellValue("A{$r}", '• ' . $note);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => $grayText]],
            ]);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template_import_jadwal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // POST /api/supervisor/schedules-import
    public function importSchedules(Request $request)
    {
        $request->validate([
            // Pakai "extensions" (bukan "mimes") karena file CSV polos sering
            // kedeteksi sistem sebagai text/plain, bukan text/csv, dan malah
            // ketolak validasi mimes walau filenya valid.
            'file'  => 'required|file|extensions:xlsx,xls,csv|max:5120',
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2020|max:2100',
        ]);

        $supervisor = $this->currentSupervisor($request);

        if (! $supervisor) {
            return response()->json([
                'message' => 'Data supervisor tidak ditemukan untuk akun ini.',
            ], 404);
        }
        $locationId = $this->supervisorLocationId($request);

if (! $locationId) {
    return response()->json([
        'message' => 'Lokasi supervisor belum ditentukan.',
    ], 403);
}

        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'File tidak bisa dibaca. Pastikan formatnya .xlsx, .xls, atau .csv dan tidak rusak.',
            ], 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'File kosong atau tidak ada data setelah baris header.',
            ], 422);
        }

        // Baris header gak selalu baris pertama — template punya judul di
        // atasnya. Cari baris yang mengandung semua kolom wajib.
        $headerRowIndex = null;
        $header = [];

        foreach ($rows as $idx => $candidateRow) {
            $candidateHeader = array_map(fn ($h) => strtolower(trim((string) $h)), $candidateRow);
            $hasAllColumns = true;

            foreach (self::JADWAL_HEADER_COLUMNS as $column) {
                if (! in_array($column, $candidateHeader, true)) {
                    $hasAllColumns = false;
                    break;
                }
            }

            if ($hasAllColumns) {
                $headerRowIndex = $idx;
                $header = $candidateHeader;
                break;
            }
        }

        if ($headerRowIndex === null) {
            return response()->json([
                'message' => 'Baris header (No, Nama, Jabatan, NIPKWT, Rute) tidak ditemukan di file. Pakai template yang disediakan supaya nama kolomnya pas.',
            ], 422);
        }

        $fixedColumnIndex = [];

        foreach (self::JADWAL_HEADER_COLUMNS as $column) {
            $fixedColumnIndex[$column] = array_search($column, $header, true);
        }

        // Kolom tanggal dikenali dari header yang isinya angka 1-31.
        $dayColumnIndex = [];

        foreach ($header as $index => $label) {
            if (in_array($index, $fixedColumnIndex, true)) {
                continue;
            }

            if (ctype_digit($label) && (int) $label >= 1 && (int) $label <= 31) {
                $dayColumnIndex[(int) $label] = $index;
            }
        }

        if (empty($dayColumnIndex)) {
            return response()->json([
                'message' => 'Tidak ada kolom tanggal (1-31) yang terbaca di file. Pakai template yang disediakan.',
            ], 422);
        }

        $imported = 0;
        $errors = [];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNumber = $i + 1; // +1 karena index array mulai dari 0

            $isEmptyRow = collect($row)->every(fn ($v) => trim((string) $v) === '');

            if ($isEmptyRow) {
                continue;
            }

            $nama = trim((string) ($row[$fixedColumnIndex['nama']] ?? ''));
            $jabatanRaw = trim((string) ($row[$fixedColumnIndex['jabatan']] ?? ''));
            $nipkwtRaw = trim((string) ($row[$fixedColumnIndex['nipkwt']] ?? ''));
            $routeName = trim((string) ($row[$fixedColumnIndex['rute']] ?? ''));

            // NIPKWT kadang kebaca sebagai angka (float) oleh Excel, rapikan
            // jadi string digit murni supaya cocok dengan yang tersimpan.
            $nipkwt = is_numeric($nipkwtRaw) ? (string) (int) $nipkwtRaw : $nipkwtRaw;

            // Kalau Nama & NIPKWT dua-duanya kosong, anggap sudah keluar dari
            // blok data (misal masuk ke bagian legenda/catatan di bawah
            // tabel) — berhenti baca sama sekali, bukan cuma dilewati.
            if ($nama === '' && $nipkwt === '') {
                break;
            }

            if ($nipkwt === '' || $routeName === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'NIPKWT dan Rute wajib diisi.'];
                continue;
            }

            $user = User::where('nipkwt', $nipkwt)->first();

            if (! $user) {
                $errors[] = ['row' => $rowNumber, 'message' => "NIPKWT '{$nipkwt}' ({$nama}) tidak ditemukan di sistem."];
                continue;
            }
            if ((int) $user->location_id !== (int) $locationId) {
    $errors[] = [
        'row' => $rowNumber,
        'message' => "{$user->name} (NIPKWT {$nipkwt}) berada di lokasi berbeda dari supervisor.",
    ];
    continue;
}

            if (! in_array($user->role, ['satpam', 'katim'])) {
                $errors[] = ['row' => $rowNumber, 'message' => "{$user->name} (NIPKWT {$nipkwt}) rolenya '{$user->role}', bukan satpam/katim, tidak bisa diberi jadwal patroli."];
                continue;
            }

            $satpam = Satpam::where('user_id', $user->id)->first();

            if (! $satpam) {
                $errors[] = ['row' => $rowNumber, 'message' => "Data satpam untuk {$user->name} (NIPKWT {$nipkwt}) tidak ditemukan."];
                continue;
            }

            $expectedJabatan = $user->role === 'katim' ? "KATIM {$user->tim}" : 'PAM';

            if ($jabatanRaw !== '' && strcasecmp(trim($jabatanRaw), $expectedJabatan) !== 0) {
                $errors[] = ['row' => $rowNumber, 'message' => "Jabatan '{$jabatanRaw}' untuk {$user->name} tidak sesuai data sistem (harusnya '{$expectedJabatan}')."];
                continue;
            }

            $route = PatrolRoute::with('points')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($routeName))])
                ->first();

            if (! $route) {
                $errors[] = ['row' => $rowNumber, 'message' => "Rute '{$routeName}' tidak ditemukan."];
                continue;
            }

            if ($route->points->isEmpty()) {
                $errors[] = ['row' => $rowNumber, 'message' => "Rute '{$routeName}' belum punya titik patroli."];
                continue;
            }

            foreach ($dayColumnIndex as $day => $colIndex) {
                if (! checkdate($bulan, $day, $tahun)) {
                    continue; // tanggal gak ada di bulan ini (mis. 31 di bulan 30 hari)
                }

                $code = strtoupper(trim((string) ($row[$colIndex] ?? '')));

                if ($code === '' || $code === 'L') {
                    continue; // libur / kosong = gak ada jadwal hari itu
                }

                if (! isset(self::SHIFT_TIMES[$code])) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => "{$user->name} (NIPKWT {$nipkwt}) tanggal {$day}: kode '{$code}' tidak dikenali (harus P/S/M/L).",
                    ];
                    continue;
                }

                $date = Carbon::create($tahun, $bulan, $day)->toDateString();

                $alreadyExists = ScheduleDetail::where('satpam_id', $satpam->id)
                    ->whereHas('schedule', fn ($q) => $q->whereDate('start_date', $date)->whereDate('end_date', $date))
                    ->exists();

                if ($alreadyExists) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => "{$user->name} (NIPKWT {$nipkwt}) tanggal {$day}: sudah ada jadwal, dilewati.",
                    ];
                    continue;
                }

                [$shiftStart, $shiftEnd] = self::SHIFT_TIMES[$code];

                DB::transaction(function () use ($supervisor, $satpam, $user, $route, $date, $shiftStart, $shiftEnd) {
                    $schedule = Schedule::create([
                        'supervisor_id' => $supervisor->id,
                        'title'         => "Jadwal {$user->name} - {$route->name}",
                        'description'   => null,
                        'start_date'    => $date,
                        'end_date'      => $date,
                        'status'        => 'aktif',
                    ]);

                    foreach ($route->points as $index => $routePoint) {
                        ScheduleDetail::create([
                            'schedule_id'     => $schedule->id,
                            'satpam_id'       => $satpam->id,
                            'patrol_point_id' => $routePoint->patrol_point_id,
                            'shift_start'     => $shiftStart,
                            'shift_end'       => $shiftEnd,
                            'sequence_order'  => $index + 1,
                        ]);
                    }
                });

                $imported++;
            }
        }

        return response()->json([
            'message'  => "Import selesai. {$imported} jadwal harian berhasil ditambahkan, " . count($errors) . ' bermasalah.',
            'imported' => $imported,
            'failed'   => count($errors),
            'errors'   => $errors,
        ]);
    }
}
