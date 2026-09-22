<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatrolLog;
use App\Models\PatrolPoint;
use App\Models\Report;
use App\Models\Satpam;
use App\Models\SkipReason;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\Schedule;
use App\Models\ScheduleDetail;

class SatpamController extends Controller
{
    
    // GET /api/satpam/summary
public function summary(Request $request)
{
    $satpam = Satpam::where('user_id', $request->user()->id)->first();

    if (! $satpam) {
        return response()->json([
            'scheduled'     => 0,
            'completed'     => 0,
            'remaining'     => 0,
            'skip'          => 0,
            'anomaly'       => 0,
            'nipkwt'        => $request->user()->nipkwt,
            'current_shift' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ambil jadwal patroli aktif hari ini
    |--------------------------------------------------------------------------
    */

    $scheduleDetails = ScheduleDetail::where('satpam_id', $satpam->id)
        ->whereHas('schedule', function ($query) {
            $query->where('status', 'aktif')
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today());
        })
        ->orderBy('sequence_order')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Total titik patroli yang dijadwalkan
    |--------------------------------------------------------------------------
    */

    $scheduled = $scheduleDetails->count();

    /*
    |--------------------------------------------------------------------------
    | Ambil log patroli hari ini
    |--------------------------------------------------------------------------
    */

    $todayLogs = PatrolLog::where('satpam_id', $satpam->id)
        ->whereDate('scan_time', today())
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Hitung status
    |--------------------------------------------------------------------------
    */

   $completed = $todayLogs
    ->whereIn('scan_status', [
        'berhasil',
        'terlambat',
        'terlewat',
        'skip',
        'anomali',
    ])
    ->whereNotNull('schedule_detail_id')
    ->pluck('schedule_detail_id')
    ->unique()
    ->count();

    $skip = $todayLogs
        ->where('scan_status', 'skip')
        ->count();

    $anomaly = $todayLogs
        ->where('scan_status', 'anomali')
        ->count();

    /*
    |--------------------------------------------------------------------------
    | Titik yang sudah disentuh / diproses
    |
    | berhasil   -> sudah dilewati
    | terlambat  -> sudah dilewati
    | skip       -> sudah diproses
    | anomali    -> sudah diproses
    | terlewat   -> sudah dilewati karena melompati titik
    |--------------------------------------------------------------------------
    */

    $processedScheduleDetailIds = $todayLogs
        ->whereNotNull('schedule_detail_id')
        ->pluck('schedule_detail_id')
        ->unique();

    /*
    |--------------------------------------------------------------------------
    | Sisa = titik jadwal yang BELUM memiliki log
    |--------------------------------------------------------------------------
    */

    $remaining = $scheduleDetails
        ->whereNotIn('id', $processedScheduleDetailIds)
        ->count();

    return response()->json([
        'scheduled'     => $scheduled,
        'completed'     => $completed,
        'remaining'     => $remaining,
        'skip'          => $skip,
        'anomaly'       => $anomaly,
        'nipkwt'        => $request->user()->nipkwt,
        'current_shift' => $this->currentShift($satpam),
    ]);
}

    /**
     * Hitung jendela waktu [mulai, selesai] sebuah shift, dengan anchor hari
     * tertentu. Shift yang lintas tengah malam (mis. Malam 22:00-06:00)
     * otomatis digeser +1 hari untuk jam selesainya. Return null kalau
     * $anchor di luar rentang tanggal jadwalnya (start_date/end_date).
     */
    private function shiftWindowOn(ScheduleDetail $detail, \Carbon\Carbon $anchor): ?array
    {
        $schedule = $detail->schedule;

        if (! $schedule) {
            return null;
        }

        if ($anchor->lt(\Carbon\Carbon::parse($schedule->start_date)) || $anchor->gt(\Carbon\Carbon::parse($schedule->end_date))) {
            return null;
        }

        $start = \Carbon\Carbon::parse($anchor->toDateString() . ' ' . $detail->shift_start);
        $end   = \Carbon\Carbon::parse($anchor->toDateString() . ' ' . $detail->shift_end);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * Cari jadwal (schedule_details) satpam yang jam-nya sedang berlangsung
     * saat ini (bukan cuma aktif hari ini, tapi benar-benar di antara
     * shift_start dan shift_end sekarang). Anchor dicoba hari ini DAN
     * kemarin, supaya shift Malam yang mulai kemarin dan masih berlangsung
     * lewat tengah malam tetap kebaca.
     */
    private function currentShift(Satpam $satpam): ?array
    {
        $details = ScheduleDetail::with('patrolPoint', 'schedule')
            ->where('satpam_id', $satpam->id)
            ->whereHas('schedule', function ($query) {
                $query->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today()->copy()->subDay());
            })
            ->get();

        foreach ($details as $detail) {
            foreach ([today(), today()->copy()->subDay()] as $anchor) {
                $window = $this->shiftWindowOn($detail, $anchor);

                if ($window && now()->between($window[0], $window[1])) {
                    return $this->formatShiftInfo($detail);
                }
            }
        }

        return null;
    }

    /**
     * Cari jadwal (schedule_details) satpam untuk titik patroli tertentu
     * yang paling relevan dengan waktu sekarang. Dipakai saat scan/skip
     * terjadi untuk tahu shift mana yang berlaku. Return array
     * [ScheduleDetail, Carbon $shiftStart, Carbon $shiftEnd] — start/end
     * bisa null kalau shift-nya belum/tidak bisa ditentukan jendela waktunya
     * (fallback, misal scan jauh lebih awal dari jadwal).
     */
    private function resolveScheduleDetail(int $satpamId, int $patrolPointId): ?array
    {
        $candidates = ScheduleDetail::with(['patrolPoint', 'schedule'])
            ->where('satpam_id', $satpamId)
            ->where('patrol_point_id', $patrolPointId)
            ->whereHas('schedule', function ($query) {
                $query->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today()->copy()->subDay());
            })
            ->get();

        $fallback = null;

        foreach ($candidates as $detail) {
            foreach ([today(), today()->copy()->subDay()] as $anchor) {
                $window = $this->shiftWindowOn($detail, $anchor);

                if (! $window) {
                    continue;
                }

                [$start, $end] = $window;

                if (now()->between($start, $end)) {
                    return [$detail, $start, $end];
                }

                // Simpan kandidat yang shift-nya sudah lewat, ambil yang
                // paling baru berakhir — dipakai buat kasus scan telat.
                if ($end->lessThan(now()) && (! $fallback || $end->greaterThan($fallback[2]))) {
                    $fallback = [$detail, $start, $end];
                }
            }
        }

        if ($fallback) {
            return $fallback;
        }

        return $candidates->isNotEmpty() ? [$candidates->first(), null, null] : null;
    }

    /**
     * Versi ringkas dari resolveScheduleDetail() — dipakai di tempat yang
     * cuma butuh ScheduleDetail-nya aja (bukan jendela waktunya).
     */
    private function findScheduleDetail(int $satpamId, int $patrolPointId): ?ScheduleDetail
    {
        return $this->resolveScheduleDetail($satpamId, $patrolPointId)[0] ?? null;
    }

    private function formatShiftInfo(?ScheduleDetail $detail): ?array
    {
        if (! $detail) {
            return null;
        }

        return [
            'label'       => $detail->shift_label,
            'shift_start' => substr($detail->shift_start, 0, 5),
            'shift_end'   => substr($detail->shift_end, 0, 5),
            'area'        => $detail->patrolPoint?->name,
        ];
    }

    /**
     * Rute patroli harus di-scan berurutan (sesuai sequence_order dalam satu
     * hari untuk satpam yang sama). Kalau satpam scan/skip titik dengan
     * urutan lebih besar sementara titik-titik sebelumnya di hari itu belum
     * pernah tersentuh sama sekali, titik-titik itu otomatis dicatat sebagai
     * "terlewat".
     *
     * Return: daftar nama titik yang baru saja ditandai terlewat.
     */
    private function markEarlierCheckpointsAsMissed(Satpam $satpam, ?ScheduleDetail $currentDetail): array
    {
        if (! $currentDetail || ! $currentDetail->schedule || $currentDetail->sequence_order <= 1) {
            return [];
        }

        $earlierDetails = ScheduleDetail::with('patrolPoint')
            ->where('satpam_id', $satpam->id)
            ->where('sequence_order', '<', $currentDetail->sequence_order)
            ->whereHas('schedule', function ($query) use ($currentDetail) {
                $query->where('status', 'aktif')
                    ->where('start_date', $currentDetail->schedule->start_date);
            })
            ->get();

        $missedNames = [];

        foreach ($earlierDetails as $earlier) {
            $alreadyLogged = PatrolLog::where('schedule_detail_id', $earlier->id)
                ->whereDate('scan_time', today())
                ->exists();

            if ($alreadyLogged) {
                continue;
            }

            PatrolLog::create([
                'satpam_id'          => $satpam->id,
                'patrol_point_id'    => $earlier->patrol_point_id,
                'schedule_detail_id' => $earlier->id,
                'scan_time'          => now(),
                'latitude'           => $earlier->patrolPoint?->latitude,
                'longitude'          => $earlier->patrolPoint?->longitude,
                'scan_status'        => 'terlewat',
                'note'               => 'Titik dilewati karena satpam sudah scan titik berikutnya terlebih dahulu.',
            ]);

            $missedNames[] = $earlier->patrolPoint?->name ?? "Titik {$earlier->sequence_order}";
        }

        return $missedNames;
    }
    // GET /api/satpam/schedule?date=YYYY-MM-DD
public function schedule(Request $request)
{
    $satpam = Satpam::where('user_id', $request->user()->id)->first();

    if (! $satpam) {
        return response()->json([
            'message' => 'Data satpam tidak ditemukan untuk akun ini.',
        ], 404);
    }

    $request->validate([
        'date' => 'nullable|date',
    ]);

    $date = $request->filled('date')
        ? \Carbon\Carbon::parse($request->input('date'))->startOfDay()
        : today();

    $scheduleDetails = \App\Models\ScheduleDetail::with([
        'schedule:id,title,description,start_date,end_date,status',
        'patrolPoint:id,name,location_address',
    ])
        ->where('satpam_id', $satpam->id)
        ->whereHas('schedule', function ($query) use ($date) {
            $query->where('status', 'aktif')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date);
        })
        ->orderBy('sequence_order')
        ->get();

    // Sisipkan status scan (kalau ada) untuk tiap titik pada tanggal yang
    // dipilih, biar satpam bisa lihat titik mana yang sudah berhasil/skip/dll
    // langsung dari halaman Jadwal Saya.
    $logsByPoint = PatrolLog::where('satpam_id', $satpam->id)
        ->whereDate('scan_time', $date)
        ->whereIn('patrol_point_id', $scheduleDetails->pluck('patrol_point_id'))
        ->orderBy('scan_time')
        ->get()
        ->groupBy('patrol_point_id');

    $scheduleDetails->each(function (\App\Models\ScheduleDetail $detail) use ($logsByPoint) {
        $pointLogs = $logsByPoint->get($detail->patrol_point_id, collect());
        $log = $pointLogs->first(fn ($log) => in_array($log->scan_status, self::FINAL_SCAN_STATUSES))
            ?? $pointLogs->last();

        $detail->scan_status = $log?->scan_status;
        $detail->scan_status_label = $log ? $this->scanStatusLabel($log->scan_status) : null;
        $detail->scan_time = $log?->scan_time;
    });

    return response()->json([
        'message' => 'Jadwal patroli berhasil diambil.',
        'date' => $date->toDateString(),
        'schedule' => $scheduleDetails,
    ]);
}


// GET /api/satpam/history?date=YYYY-MM-DD|all
public function history(Request $request)
{
    $satpam = Satpam::where('user_id', $request->user()->id)->first();

    if (! $satpam) {
        return response()->json([
            'message' => 'Data satpam tidak ditemukan untuk akun ini.',
        ], 404);
    }

    $request->validate([
        'date' => 'nullable|string',
    ]);

    $dateParam = $request->input('date');
    $isAll = $dateParam === 'all';

    $query = PatrolLog::with([
        'patrolPoint',
        'skipReason',
        'report',
        'scheduleDetail',
    ])->where('satpam_id', $satpam->id);

    if (! $isAll) {
        $date = $dateParam ? \Carbon\Carbon::parse($dateParam) : today();
        $query->whereDate('scan_time', $date);
    }

    $history = $query->orderBy('scan_time', 'desc')->get();

    return response()->json([
        'message' => 'Riwayat patroli berhasil diambil.',
        'date' => $isAll ? 'all' : ($dateParam ?: today()->toDateString()),
        'history' => $history,
    ]);
}
    // GET /api/satpam/patrol-points
    public function patrolPoints()
    {
        $patrolPoints = PatrolPoint::where('status', 'aktif')
            ->orderBy('name')
            ->get(['id', 'name', 'location_address']);

        return response()->json([
            'patrol_points' => $patrolPoints,
        ]);
    }

    // GET /api/satpam/skip-options
    // Titik yang boleh di-skip: cuma titik yang ada di jadwal/rute satpam
    // hari ini, dan yang belum punya log sama sekali hari ini (belum
    // discan/di-skip/dsb). Titik yang sudah diproses gak ditawarkan lagi.
    public function skipOptions(Request $request)
    {
        $satpam = Satpam::where('user_id', $request->user()->id)->first();

        if (! $satpam) {
            return response()->json([
                'message' => 'Data satpam tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $scheduleDetails = ScheduleDetail::with('patrolPoint')
            ->where('satpam_id', $satpam->id)
            ->whereHas('schedule', function ($query) {
                $query->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today());
            })
            ->orderBy('sequence_order')
            ->get();

        // Titik dianggap "sudah diproses" kalau punya log hari ini dengan
        // status final (berhasil/terlambat/skip) untuk titik itu — gak
        // peduli lewat schedule_detail yang mana. Status anomali gak
        // dihitung selesai, jadi titiknya tetap muncul sebagai opsi. Satpam
        // bisa punya lebih dari 1 jadwal/rute hari yang sama yang kebetulan
        // memuat titik yang sama, jadi pengecualiannya harus per titik fisik
        // (patrol_point_id), bukan per baris schedule_detail.
        $loggedPatrolPointIds = PatrolLog::where('satpam_id', $satpam->id)
            ->whereDate('scan_time', today())
            ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
            ->pluck('patrol_point_id')
            ->unique();

        $points = $scheduleDetails
            ->whereNotIn('patrol_point_id', $loggedPatrolPointIds)
            ->unique('patrol_point_id')
            ->sortBy('sequence_order')
            ->map(fn (ScheduleDetail $detail) => [
                'patrol_point_id' => $detail->patrol_point_id,
                'name'            => $detail->patrolPoint?->name ?? '-',
                'sequence_order'  => $detail->sequence_order,
            ])
            ->values();

        return response()->json([
            'points' => $points,
        ]);
    }

    // POST /api/satpam/scan
    public function scan(Request $request)
    {
        $validated = $request->validate([
            'qr_code'   => 'required|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $satpam = Satpam::where('user_id', $request->user()->id)->first();

        if (! $satpam) {
            return response()->json([
                'message' => 'Data satpam tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $patrolPoint = PatrolPoint::where('qr_code', $validated['qr_code'])
            ->where('status', 'aktif')
            ->first();

        if (! $patrolPoint) {
            return response()->json([
                'message' => 'QR Code tidak dikenali atau titik patroli tidak aktif.',
            ], 404);
        }

        // Titik yang di-scan harus bagian dari jadwal/rute satpam hari ini.
        // Kalau bukan, tolak dari awal — jangan pernah masuk ke database.
        $resolved = $this->resolveScheduleDetail($satpam->id, $patrolPoint->id);

        if (! $resolved) {
            return response()->json([
                'message' => 'Titik ini bukan bagian dari jadwal patroli Anda hari ini.',
            ], 422);
        }

        [$scheduleDetail, , $shiftEndAt] = $resolved;

        // Titik yang statusnya sudah final (berhasil/terlambat/skip) hari ini
        // tidak boleh discan lagi. Khusus anomali dianggap belum selesai,
        // jadi satpam boleh mencoba scan ulang.
        $existingLog = PatrolLog::where('satpam_id', $satpam->id)
            ->where('patrol_point_id', $patrolPoint->id)
            ->whereDate('scan_time', today())
            ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
            ->first();

        if ($existingLog) {
            return response()->json([
                'message' => $this->alreadyProcessedMessage($existingLog->scan_status),
            ], 422);
        }

        $distance = null;

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $distance = $this->distanceInMeters(
                $validated['latitude'],
                $validated['longitude'],
                $patrolPoint->latitude,
                $patrolPoint->longitude
            );
        }

        $isOutOfRange = $distance !== null && $distance > $patrolPoint->radius_meters;

        // Cek apakah scan dilakukan setelah shift-nya berakhir (terlambat).
        // $shiftEndAt sudah memperhitungkan shift yang lintas tengah malam
        // (mis. Malam 22:00-06:00) lewat resolveScheduleDetail().
        $isLate = $shiftEndAt !== null && now()->greaterThan($shiftEndAt);

        if ($isOutOfRange) {
            $scanStatus = 'anomali';
            $note = 'Jarak scan di luar radius titik patroli.';
        } elseif ($isLate) {
            $scanStatus = 'terlambat';
            $note = 'Scan dilakukan setelah shift berakhir.';
        } else {
            $scanStatus = 'berhasil';
            $note = null;
        }

        $patrolLog = PatrolLog::create([
            'satpam_id'           => $satpam->id,
            'patrol_point_id'     => $patrolPoint->id,
            'schedule_detail_id'  => $scheduleDetail?->id,
            'scan_time'           => now(),
            'latitude'            => $validated['latitude'] ?? $patrolPoint->latitude,
            'longitude'           => $validated['longitude'] ?? $patrolPoint->longitude,
            'distance_from_point' => $distance,
            'scan_status'         => $scanStatus,
            'note'                => $note,
        ]);

        $skippedPoints = $this->markEarlierCheckpointsAsMissed($satpam, $scheduleDetail);

        $messages = [
            'anomali'   => 'Scan tercatat, tapi lokasi Anda di luar radius titik patroli.',
            'terlambat' => 'Scan tercatat, tapi Anda terlambat dari jadwal shift.',
            'berhasil'  => 'Scan berhasil.',
        ];

        return response()->json([
            'message'     => $messages[$scanStatus],
            'scan_status' => $scanStatus,
            'patrol_log'  => $patrolLog,
            'patrol_point' => [
                'id'               => $patrolPoint->id,
                'name'             => $patrolPoint->name,
                'code'             => 'TP-' . str_pad($patrolPoint->id, 3, '0', STR_PAD_LEFT),
                'location_address' => $patrolPoint->location_address,
            ],
            'shift'          => $this->formatShiftInfo($scheduleDetail),
            'skipped_points' => $skippedPoints,
        ]);
    }

    private function distanceInMeters($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    // POST /api/satpam/skip-scan
    public function skipScan(Request $request)
    {
        $validated = $request->validate([
            'patrol_point_id' => 'required|exists:patrol_points,id',
            'reason'          => 'required|string|max:255',
        ]);

        $satpam = Satpam::where('user_id', $request->user()->id)->first();

        if (! $satpam) {
            return response()->json([
                'message' => 'Data satpam tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $patrolPoint = PatrolPoint::findOrFail($validated['patrol_point_id']);

        $scheduleDetail = $this->findScheduleDetail($satpam->id, $patrolPoint->id);

        if (! $scheduleDetail) {
            return response()->json([
                'message' => 'Titik ini bukan bagian dari jadwal patroli Anda hari ini.',
            ], 422);
        }

        $existingLog = PatrolLog::where('satpam_id', $satpam->id)
            ->where('patrol_point_id', $patrolPoint->id)
            ->whereDate('scan_time', today())
            ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
            ->first();

        if ($existingLog) {
            return response()->json([
                'message' => $this->alreadyProcessedMessage($existingLog->scan_status),
            ], 422);
        }

        $patrolLog = PatrolLog::create([
            'satpam_id'          => $satpam->id,
            'patrol_point_id'    => $patrolPoint->id,
            'schedule_detail_id' => $scheduleDetail?->id,
            'scan_time'          => now(),
            'latitude'           => $patrolPoint->latitude,
            'longitude'          => $patrolPoint->longitude,
            'scan_status'        => 'skip',
            'note'               => $validated['reason'],
            'review_status'      => 'pending',
        ]);

        SkipReason::create([
            'patrol_log_id' => $patrolLog->id,
            'reason'        => $validated['reason'],
        ]);

        $skippedPoints = $this->markEarlierCheckpointsAsMissed($satpam, $scheduleDetail);

        return response()->json([
            'message'        => 'Skip scan berhasil dicatat.',
            'patrol_log'     => $patrolLog,
            'shift'          => $this->formatShiftInfo($scheduleDetail),
            'skipped_points' => $skippedPoints,
        ], 201);
    }

    // Status yang dianggap "sudah final" untuk sebuah titik hari itu — kalau
    // titik sudah punya log dengan salah satu status ini, gak boleh
    // discan/di-skip lagi. 'anomali' sengaja gak dimasukkan karena dianggap
    // belum selesai (satpam masih boleh coba scan ulang).
    private const FINAL_SCAN_STATUSES = ['berhasil', 'terlambat', 'skip'];

    private function alreadyProcessedMessage(string $scanStatus): string
    {
        return match ($scanStatus) {
            'skip' => 'Anda sudah skip titik ini hari ini.',
            'terlambat' => 'Titik ini sudah di-scan (terlambat) hari ini.',
            default => 'Titik ini sudah di-scan hari ini.',
        };
    }

    private function scanStatusLabel(string $scanStatus): string
    {
        return match ($scanStatus) {
            'berhasil' => 'Scan Berhasil',
            'terlambat' => 'Scan Terlambat',
            'skip' => 'Di-skip',
            'anomali' => 'Anomali',
            'terlewat' => 'Terlewat',
            default => $scanStatus,
        };
    }

    private const KONDISI_LABELS = [
        'aman'          => 'Aman',
        'mencurigakan'  => 'Mencurigakan',
        'kerusakan'     => 'Ada Kerusakan',
        'darurat'       => 'Darurat',
    ];

    private const REPORT_TYPE_LABELS = [
        'rutin'   => 'Patroli Rutin',
        'insiden' => 'Insiden',
        'temuan'  => 'Temuan',
    ];

    // POST /api/satpam/reports
    public function createReport(Request $request)
    {
        $validated = $request->validate([
            'patrol_log_id' => 'required|exists:patrol_logs,id',
            'report_type'   => 'required|in:rutin,insiden,temuan',
            'kondisi'       => 'required|in:' . implode(',', array_keys(self::KONDISI_LABELS)),
            'description'   => 'nullable|string|max:300',
            // 20MB — foto langsung dari kamera HP gampang lebih dari 5MB.
            // Ukuran akhirnya bakal jauh lebih kecil kok karena di-resize +
            // dikonversi ke WebP di convertPhotoToWebp().
            'photo'         => 'nullable|image|max:20480',
        ]);

        $satpam = Satpam::where('user_id', $request->user()->id)->first();

        if (! $satpam) {
            return response()->json([
                'message' => 'Data satpam tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $patrolLog = PatrolLog::where('id', $validated['patrol_log_id'])
            ->where('satpam_id', $satpam->id)
            ->first();

        if (! $patrolLog) {
            return response()->json([
                'message' => 'Data scan tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $photoPath = null;

        if ($request->hasFile('photo')) {
            try {
                $photoPath = $this->convertPhotoToWebp($request->file('photo'));
            } catch (\Throwable $exception) {
                report($exception);

                throw ValidationException::withMessages([
                    'photo' => 'Foto tidak dapat dikonversi ke format WebP.',
                ]);
            }
        }

        $title = self::REPORT_TYPE_LABELS[$validated['report_type']]
            . ' - ' . self::KONDISI_LABELS[$validated['kondisi']];

        $report = Report::create([
            'patrol_log_id' => $patrolLog->id,
            'report_type'   => $validated['report_type'],
            'title'         => $title,
            'description'   => $validated['description'] ?? null,
            'photo'         => $photoPath,
            'review_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Laporan berhasil disimpan.',
            'report'  => $report,
        ], 201);
    }

    /**
     * Convert the uploaded image to WebP and store only the resulting path.
     */
    private function convertPhotoToWebp(UploadedFile $photo): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('PHP GD with WebP support is required.');
        }

        $imageInfo = @getimagesize($photo->getRealPath());
        $mime = $imageInfo['mime'] ?? null;

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($photo->getRealPath()),
            'image/png'  => @imagecreatefrompng($photo->getRealPath()),
            'image/gif'  => @imagecreatefromgif($photo->getRealPath()),
            'image/webp' => @imagecreatefromwebp($photo->getRealPath()),
            default      => false,
        };

        if ($image === false) {
            throw new \RuntimeException('Unable to decode the uploaded image.');
        }

        try {
            // Kamera HP nyimpen orientasi potret/lanskap lewat tag EXIF
            // (pixel aslinya gak diputer) — tanpa ini foto satpam sering
            // muncul miring/kesamping di halaman laporan.
            if ($mime === 'image/jpeg') {
                $image = $this->applyExifOrientation($image, $photo->getRealPath());
            }

            // Foto kamera HP modern bisa 4000px+ di sisi terpanjang —
            // turunkan dulu biar gak berat diproses & hasil file-nya gak
            // kebesaran buat foto laporan.
            $image = $this->downscale($image, 1600);

            // Keep transparent backgrounds when the source image has an alpha channel.
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $encoded = imagewebp($image, null, 85);
            $contents = ob_get_clean();

            if (! $encoded || $contents === false || $contents === '') {
                throw new \RuntimeException('Unable to encode the image as WebP.');
            }

            $path = 'reports/' . Str::uuid() . '.webp';

            // Disimpan di Supabase Storage (disk "s3", S3-compatible) karena
            // disk lokal Render bersifat ephemeral — file hilang tiap
            // container restart/redeploy. Return full URL-nya langsung
            // supaya frontend gak perlu tau di mana file-nya disimpan.
            if (! Storage::disk('s3')->put($path, $contents)) {
                throw new \RuntimeException('Unable to store the converted image.');
            }

            return Storage::disk('s3')->url($path);
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Putar gambar sesuai tag EXIF Orientation-nya (kalau ada). Kamera HP
     * nyimpen rotasi lewat metadata ini, bukan beneran muter pixel-nya.
     */
    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated === false) {
            return $image;
        }

        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }

    /**
     * Turunkan resolusi kalau sisi terpanjangnya lebih dari $maxSide, biar
     * gambar dari kamera HP (bisa 4000px+) gak berat diproses/disimpan.
     */
    private function downscale(\GdImage $image, int $maxSide): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $maxSide) {
            return $image;
        }

        $ratio = $maxSide / max($width, $height);
        $resized = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio), IMG_BILINEAR_FIXED);

        if ($resized === false) {
            return $image;
        }

        imagedestroy($image);

        return $resized;
    }
}
