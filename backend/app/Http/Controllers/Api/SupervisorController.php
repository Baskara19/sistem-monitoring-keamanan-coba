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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SupervisorController extends Controller
{
    /**
     * Data ringkas untuk halaman monitoring supervisor.
     */
    public function monitoring(Request $request)
    {
        $today = today();

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
                'offline' => Satpam::where('status', 'aktif')->count() - $activeSatpams->count(),
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
     * Ambil data Supervisor dari user yang sedang login.
     */
    private function currentSupervisor(Request $request)
    {
        return Supervisor::where('user_id', $request->user()->id)->first();
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
    public function scheduleIndex(Request $request)
    {
        $query = ScheduleDetail::with(['schedule', 'satpam.user', 'patrolPoint']);

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

        $rows = $query->join('schedules', 'schedules.id', '=', 'schedule_details.schedule_id')
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

        $route = PatrolRoute::with('points')->find($validated['route_id']);

        if (! $route || $route->points->isEmpty()) {
            return response()->json([
                'message' => 'Rute patroli tidak ditemukan atau belum punya titik.',
            ], 404);
        }

        $satpamName = Satpam::with('user')->find($validated['satpam_id'])?->user?->name ?? 'Satpam';

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

        $validated = $request->validate([
            'satpam_id'       => 'required|exists:satpams,id',
            'patrol_point_id' => 'required|exists:patrol_points,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'shift_start'     => 'required',
            'shift_end'       => 'required',
            'status'          => 'required|in:aktif,nonaktif',
        ]);

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
    public function scheduleDestroy($id)
    {
        $detail = ScheduleDetail::find($id);

        if (! $detail) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan.',
            ], 404);
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
    public function satpamList()
    {
        $satpam = Satpam::with('user:id,name')
            ->where('status', 'aktif')
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
    $query = PatrolLog::with([
        'satpam.user',
        'patrolPoint',
        'scheduleDetail.schedule',
        'scheduleDetail.patrolPoint',
        'report',
        'skipReason',
    ]);

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

            'badge_number' => $log->satpam?->badge_number ?? '-',

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
    ])
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

    return response()->json([
        'message' => 'Data laporan berhasil diambil',

        'statistics' => [
            'total_reports' => $totalReports,
            'pending_reports' => $pendingReports,
            'skip_reports' => $skipReports,
            'anomaly_reports' => $anomalyReports,
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
public function reviewReport($id)
{
    $report = Report::find($id);

    if (! $report) {
        return response()->json([
            'message' => 'Laporan tidak ditemukan.',
        ], 404);
    }

    $report->update([
        'review_status' => 'reviewed',
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
public function reviewSkip($id)
{
    $skipReason = SkipReason::find($id);

    if (! $skipReason) {
        return response()->json([
            'message' => 'Data skip tidak ditemukan.',
        ], 404);
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

    private const IMPORT_COLUMNS = [
        'satpam',
        'rute',
        'tanggal mulai',
        'tanggal selesai',
        'jam mulai',
        'jam selesai',
    ];

    // GET /api/supervisor/schedules/import-template
    public function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal');

        $sheet->fromArray(
            ['Satpam', 'Rute', 'Tanggal Mulai', 'Tanggal Selesai', 'Jam Mulai', 'Jam Selesai'],
            null,
            'A1'
        );

        $sheet->fromArray(
            ['jeppp', 'Rute A', '2026-09-03', '2026-09-03', '07:00', '15:00'],
            null,
            'A2'
        );

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setWidth(18);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template_import_jadwal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // POST /api/supervisor/schedules/import
    public function importSchedules(Request $request)
    {
        $request->validate([
            // Pakai "extensions" (bukan "mimes") karena file CSV polos sering
            // kedeteksi sistem sebagai text/plain, bukan text/csv, dan malah
            // ketolak validasi mimes walau filenya valid.
            'file' => 'required|file|extensions:xlsx,xls,csv|max:5120',
        ]);

        $supervisor = $this->currentSupervisor($request);

        if (! $supervisor) {
            return response()->json([
                'message' => 'Data supervisor tidak ditemukan untuk akun ini.',
            ], 404);
        }

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

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);

        $columnMap = [];

        foreach (self::IMPORT_COLUMNS as $column) {
            $index = array_search($column, $header, true);
            $columnMap[$column] = $index === false ? null : $index;
        }

        $missingColumns = array_keys(array_filter($columnMap, fn ($v) => $v === null));

        if (! empty($missingColumns)) {
            return response()->json([
                'message' => 'Kolom wajib tidak ditemukan: ' . implode(', ', array_map('ucwords', $missingColumns))
                    . '. Pakai template yang disediakan supaya nama kolomnya pas.',
            ], 422);
        }

        $imported = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNumber = $i + 1; // +1 karena baris pertama di file adalah header

            $isEmptyRow = collect($row)->every(fn ($v) => trim((string) $v) === '');

            if ($isEmptyRow) {
                continue;
            }

            $satpamName = trim((string) ($row[$columnMap['satpam']] ?? ''));
            $routeName = trim((string) ($row[$columnMap['rute']] ?? ''));
            $startDateRaw = trim((string) ($row[$columnMap['tanggal mulai']] ?? ''));
            $endDateRaw = trim((string) ($row[$columnMap['tanggal selesai']] ?? ''));
            $shiftStartRaw = trim((string) ($row[$columnMap['jam mulai']] ?? ''));
            $shiftEndRaw = trim((string) ($row[$columnMap['jam selesai']] ?? ''));

            if ($satpamName === '' || $routeName === '' || $startDateRaw === ''
                || $endDateRaw === '' || $shiftStartRaw === '' || $shiftEndRaw === ''
            ) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Ada kolom wajib yang kosong.'];
                continue;
            }

            $matchingSatpam = Satpam::whereHas('user', function ($query) use ($satpamName) {
                $query->whereRaw('LOWER(name) = ?', [strtolower($satpamName)]);
            })->get();

            if ($matchingSatpam->isEmpty()) {
                $errors[] = ['row' => $rowNumber, 'message' => "Satpam '{$satpamName}' tidak ditemukan."];
                continue;
            }

            if ($matchingSatpam->count() > 1) {
                $errors[] = ['row' => $rowNumber, 'message' => "Ada lebih dari 1 satpam bernama '{$satpamName}', gunakan nama yang lebih spesifik."];
                continue;
            }

            $satpam = $matchingSatpam->first();

            $route = PatrolRoute::with('points')
                ->whereRaw('LOWER(name) = ?', [strtolower($routeName)])
                ->first();

            if (! $route) {
                $errors[] = ['row' => $rowNumber, 'message' => "Rute '{$routeName}' tidak ditemukan."];
                continue;
            }

            if ($route->points->isEmpty()) {
                $errors[] = ['row' => $rowNumber, 'message' => "Rute '{$routeName}' belum punya titik patroli."];
                continue;
            }

            try {
                $startDate = $this->parseImportDate($startDateRaw);
                $endDate = $this->parseImportDate($endDateRaw);
                $shiftStart = $this->parseImportTime($shiftStartRaw);
                $shiftEnd = $this->parseImportTime($shiftEndRaw);
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Format tanggal/jam tidak valid. Pakai YYYY-MM-DD untuk tanggal dan HH:MM untuk jam.'];
                continue;
            }

            if ($endDate->lt($startDate)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'];
                continue;
            }

            DB::transaction(function () use ($supervisor, $satpam, $route, $startDate, $endDate, $shiftStart, $shiftEnd) {
                $schedule = Schedule::create([
                    'supervisor_id' => $supervisor->id,
                    'title'         => "Jadwal {$satpam->user->name} - {$route->name}",
                    'description'   => null,
                    'start_date'    => $startDate->toDateString(),
                    'end_date'      => $endDate->toDateString(),
                    'status'        => 'aktif',
                ]);

                $startingSequence = ScheduleDetail::where('satpam_id', $satpam->id)
                    ->whereHas('schedule', fn ($query) => $query->where('start_date', $startDate->toDateString()))
                    ->count();

                foreach ($route->points as $index => $routePoint) {
                    ScheduleDetail::create([
                        'schedule_id'     => $schedule->id,
                        'satpam_id'       => $satpam->id,
                        'patrol_point_id' => $routePoint->patrol_point_id,
                        'shift_start'     => $shiftStart,
                        'shift_end'       => $shiftEnd,
                        'sequence_order'  => $startingSequence + $index + 1,
                    ]);
                }
            });

            $imported++;
        }

        return response()->json([
            'message'  => "Import selesai. {$imported} jadwal berhasil ditambahkan, " . count($errors) . ' baris gagal.',
            'imported' => $imported,
            'failed'   => count($errors),
            'errors'   => $errors,
        ]);
    }

    /**
     * Baca tanggal dari cell Excel/CSV. Excel kadang nyimpen tanggal sebagai
     * angka serial (kalau cell-nya diformat sebagai Date), jadi ditangani dua-duanya.
     */
    private function parseImportDate(string $value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        return Carbon::parse($value);
    }

    private function parseImportTime(string $value): string
    {
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i');
        }

        return Carbon::parse(str_replace('.', ':', $value))->format('H:i');
    }
}