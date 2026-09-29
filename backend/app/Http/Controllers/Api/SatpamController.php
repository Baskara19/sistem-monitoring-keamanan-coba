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
use App\Models\PatrolHandover;
use App\Services\PatrolRoundService;

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
    | Ambil jadwal patroli aktif hari ini.
    | Gunakan subDay() agar shift Malam (22:00–06:00) yang dimulai kemarin
    | tetap terbaca saat satpam cek summary di dini hari.
    |--------------------------------------------------------------------------
    */

    $scheduleDetails = ScheduleDetail::with('schedule')
        ->where(function ($query) use ($satpam) {
            $query->where('satpam_id', $satpam->id)
                  ->orWhereHas('schedule', fn ($q) => $q->where('katim_id', $satpam->id));
        })
        ->whereHas('schedule', function ($query) {
            $query->where('status', 'aktif')
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today()->copy()->subDay());
        })
        ->orderBy('sequence_order')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Total target = jumlah titik pada putaran yang ditugaskan kepada petugas ini
    |--------------------------------------------------------------------------
    */

    $roundService = new PatrolRoundService();
    $scheduled = 0;
    foreach ($scheduleDetails as $detail) {
        for ($r = 1; $r <= 4; $r++) {
            if ($roundService->isRoundAssignedTo($detail, $r, $satpam->id)) {
                $scheduled++;
            }
        }
    }

    // Titik yang diterima via handover hari ini
    $acceptedHandoversCount = PatrolHandover::where('to_satpam_id', $satpam->id)
        ->whereDate('handover_date', today())
        ->where('status', 'accepted')
        ->count();
    $scheduled += $acceptedHandoversCount;

    /*
    |--------------------------------------------------------------------------
    | Ambil semua log yang berasal dari jadwal di atas.
    | Tidak lagi filter by date — patrol_round sudah jadi identitas putaran.
    |--------------------------------------------------------------------------
    */

    $scheduleDetailIds = $scheduleDetails->pluck('id');

    $logs = PatrolLog::where('satpam_id', $satpam->id)
        ->whereIn('schedule_detail_id', $scheduleDetailIds)
        ->whereNotNull('patrol_round')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | Hitung status berdasarkan pasangan unik (schedule_detail_id, patrol_round)
    |
    | completed = berapa pasangan (detail+round) yang sudah punya log apapun
    | remaining = target yang belum tersentuh sama sekali
    |--------------------------------------------------------------------------
    */

    // Completed: unique kombinasi detail+round yang sudah punya status aktif
    $completed = $logs
        ->whereIn('scan_status', ['berhasil', 'terlambat', 'terlewat', 'skip', 'anomali'])
        ->map(fn ($log) => $log->schedule_detail_id . '-' . $log->patrol_round)
        ->unique()
        ->count();

    $skip = $logs->where('scan_status', 'skip')->count();

    $anomaly = $logs->where('scan_status', 'anomali')->count();

    /*
    |--------------------------------------------------------------------------
    | Sisa = total target dikurangi yang sudah selesai
    |--------------------------------------------------------------------------
    */

    $remaining = max(0, $scheduled - $completed);

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
        $details = ScheduleDetail::with('patrolPoint', 'schedule.katim.user', 'satpam.user')
            ->where(function ($q) use ($satpam) {
                $q->where('satpam_id', $satpam->id)
                  ->orWhereHas('schedule', fn ($sq) => $sq->where('katim_id', $satpam->id));
            })
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
        $candidates = ScheduleDetail::with(['patrolPoint', 'schedule.katim.user', 'satpam.user'])
            ->where('patrol_point_id', $patrolPointId)
            ->where(function ($q) use ($satpamId) {
                $q->where('satpam_id', $satpamId)
                  ->orWhereHas('schedule', fn ($sq) => $sq->where('katim_id', $satpamId));
            })
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
    /**
     * Tandai titik-titik sebelumnya (sequence lebih kecil) dalam PUTARAN YANG SAMA
     * sebagai 'terlewat' jika satpam sudah melompat ke titik berikutnya.
     *
     * PENTING: Hanya cek log pada putaran ($currentRound) yang sama — jangan
     * sampai log dari putaran 1 mempengaruhi putaran 2.
     *
     * @param  int  $currentRound  Putaran aktif saat ini (1–4).
     */
    private function markEarlierCheckpointsAsMissed(
        Satpam $satpam,
        ?ScheduleDetail $currentDetail,
        int $currentRound = 1
    ): array {
        if (! $currentDetail || ! $currentDetail->schedule || $currentDetail->sequence_order <= 1) {
            return [];
        }

        // Ambil semua titik di schedule yang sama dengan urutan lebih kecil.
        $earlierDetails = ScheduleDetail::with('patrolPoint')
            ->where('schedule_id', $currentDetail->schedule_id)
            ->where('sequence_order', '<', $currentDetail->sequence_order)
            ->get();

        $missedNames = [];

        foreach ($earlierDetails as $earlier) {
            // Cek apakah titik ini sudah punya log apapun di putaran yang sama.
            // Beda putaran = sequence baru, tidak dianggap terlewat.
            $alreadyLogged = PatrolLog::where('schedule_detail_id', $earlier->id)
                ->where('patrol_round', $currentRound)
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
                'patrol_round'       => $currentRound,
                'scan_status'        => 'terlewat',
                'note'               => 'Titik dilewati karena satpam sudah scan titik berikutnya terlebih dahulu.',
            ]);

            $missedNames[] = $earlier->patrolPoint?->name ?? "Titik {$earlier->sequence_order}";
        }

        return $missedNames;
    }

    /**
     * Resolusi jadwal dan otorisasi patroli untuk titik tertentu.
     * Memeriksa jadwal langsung (Satpam / KAT) maupun delegasi resmi (PatrolHandover).
     *
     * @return array{
     *   authorized: bool,
     *   message?: string,
     *   status_code?: int,
     *   detail?: ScheduleDetail,
     *   shift_start_at?: \Carbon\Carbon|null,
     *   shift_end_at?: \Carbon\Carbon|null,
     *   round?: int,
     *   active_handover?: PatrolHandover|null
     * }
     */
    private function resolvePatrolContext(Satpam $satpam, PatrolPoint $patrolPoint): array
    {
        $roundService = new PatrolRoundService();
        $today = today();
        $yesterday = $today->copy()->subDay();

        // 1. Cari kandidat ScheduleDetail langsung dari jadwal aktif satpam / KAT
        $directCandidate = ScheduleDetail::with(['patrolPoint', 'schedule.katim.user', 'satpam.user'])
            ->where('patrol_point_id', $patrolPoint->id)
            ->where(function ($q) use ($satpam) {
                $q->where('satpam_id', $satpam->id)
                  ->orWhereHas('schedule', fn ($sq) => $sq->where('katim_id', $satpam->id));
            })
            ->whereHas('schedule', function ($query) use ($today, $yesterday) {
                $query->where('status', 'aktif')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $yesterday);
            })
            ->get();

        // 2. Cari kandidat handover yang melibatkan satpam ini sebagai penerima (to_satpam_id)
        $handoversReceived = PatrolHandover::with([
            'fromSatpam.user',
            'scheduleDetail.schedule.katim.user',
            'scheduleDetail.satpam.user',
            'scheduleDetail.patrolPoint'
        ])
            ->where('to_satpam_id', $satpam->id)
            ->where('patrol_point_id', $patrolPoint->id)
            ->whereDate('handover_date', '>=', $yesterday)
            ->whereDate('handover_date', '<=', $today)
            ->orderBy('id', 'desc')
            ->get();

        // Kumpulkan semua schedule detail yang relevan
        $allDetails = collect();
        foreach ($directCandidate as $dc) {
            $allDetails->push($dc);
        }
        foreach ($handoversReceived as $hr) {
            if ($hr->scheduleDetail) {
                $allDetails->push($hr->scheduleDetail);
            }
        }
        $allDetails = $allDetails->unique('id');

        if ($allDetails->isEmpty()) {
            return [
                'authorized'  => false,
                'message'     => 'Titik ini bukan bagian dari jadwal patroli Anda hari ini.',
                'status_code' => 422,
            ];
        }

        // Tentukan ScheduleDetail dan jendela waktu shift yang aktif saat ini
        $detail = null;
        $shiftStartAt = null;
        $shiftEndAt = null;
        $fallbackDetail = null;

        foreach ($allDetails as $cand) {
            foreach ([$today, $yesterday] as $anchor) {
                $window = $this->shiftWindowOn($cand, $anchor);
                if (! $window) {
                    continue;
                }
                [$start, $end] = $window;
                if (now()->between($start, $end)) {
                    $detail = $cand;
                    $shiftStartAt = $start;
                    $shiftEndAt = $end;
                    break 2;
                }
                if ($end->lessThan(now()) && (! $fallbackDetail || $end->greaterThan($fallbackDetail[2]))) {
                    $fallbackDetail = [$cand, $start, $end];
                }
            }
        }

        if (! $detail && $fallbackDetail) {
            [$detail, $shiftStartAt, $shiftEndAt] = $fallbackDetail;
        }

        if (! $detail) {
            $detail = $allDetails->first();
        }

        // Tentukan anchor tanggal shift dan putaran berjalan saat ini
        $shiftAnchor = $shiftStartAt ? $shiftStartAt->copy()->startOfDay() : $today;
        $activeRound = $roundService->resolveRound($detail->shift_label, $shiftAnchor, now());

        // Cek apakah ada handover yang diterima untuk jadwal dan titik ini
        $acceptedHandover = $handoversReceived
            ->where('schedule_detail_id', $detail->id)
            ->where('status', 'accepted')
            ->first(function ($h) use ($activeRound) {
                return (int) $h->patrol_round === (int) $activeRound;
            })
            ?? $handoversReceived
                ->where('schedule_detail_id', $detail->id)
                ->where('status', 'accepted')
                ->first(function ($h) {
                    return ! PatrolLog::where('patrol_handover_id', $h->id)
                        ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
                        ->exists();
                });

        $activeHandover = null;
        $round = $activeRound;

        if ($acceptedHandover) {
            // Otorisasi via handover yang telah diterima (accepted)
            $activeHandover = $acceptedHandover;
            $round = $acceptedHandover->patrol_round;
        } else {
            // Cek apakah ada handover tetapi statusnya belum diterima (pending/rejected/cancelled)
            $nonAcceptedHandover = $handoversReceived
                ->where('schedule_detail_id', $detail->id)
                ->where('patrol_round', $activeRound)
                ->first()
                ?? $handoversReceived->where('schedule_detail_id', $detail->id)->first();

            if ($nonAcceptedHandover) {
                if ($nonAcceptedHandover->status === 'pending') {
                    return [
                        'authorized'  => false,
                        'message'     => 'Permintaan handover untuk titik ini belum Anda terima. Silakan terima handover terlebih dahulu.',
                        'status_code' => 422,
                    ];
                } elseif ($nonAcceptedHandover->status === 'rejected') {
                    return [
                        'authorized'  => false,
                        'message'     => 'Permintaan handover untuk titik ini telah ditolak.',
                        'status_code' => 422,
                    ];
                } elseif ($nonAcceptedHandover->status === 'cancelled') {
                    return [
                        'authorized'  => false,
                        'message'     => 'Permintaan handover untuk titik ini telah dibatalkan oleh pemohon.',
                        'status_code' => 422,
                    ];
                }
            }

            // Validasi penugasan langsung pada putaran ini
            if (! $roundService->isRoundAssignedTo($detail, $round, $satpam->id)) {
                $assignedInfo = $roundService->getAssignedSatpamInfo($detail, $round);
                $assignedRole = $assignedInfo['role'] === 'katim' ? 'KAT' : 'Satpam';
                $assignedName = $assignedInfo['name'];
                return [
                    'authorized'  => false,
                    'message'     => "Putaran {$round} adalah tugas {$assignedRole} ({$assignedName}). Anda tidak ditugaskan pada putaran ini.",
                    'status_code' => 422,
                ];
            }

            // Jika petugas yang ditugaskan sudah mengalihkan titik ini ke rekan (status accepted), petugas asal dilarang scan
            $handoverSent = PatrolHandover::with('toSatpam.user')
                ->where('from_satpam_id', $satpam->id)
                ->where('schedule_detail_id', $detail->id)
                ->where('patrol_round', $round)
                ->where('status', 'accepted')
                ->whereDate('handover_date', $shiftAnchor->toDateString())
                ->first();

            if ($handoverSent) {
                $partnerName = $handoverSent->toSatpam?->user?->name ?? 'rekan satpam';
                return [
                    'authorized'  => false,
                    'message'     => "Titik ini pada Putaran {$round} sudah Anda alihkan (handover) kepada {$partnerName}. Anda tidak dapat melakukan scan pada titik ini.",
                    'status_code' => 422,
                ];
            }
        }

        // Cek scan ganda: titik sudah selesai diproses (berhasil/terlambat/skip) pada putaran yang sama
        $existingLog = PatrolLog::where('schedule_detail_id', $detail->id)
            ->where('patrol_round', $round)
            ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
            ->first();

        if ($existingLog) {
            return [
                'authorized'  => false,
                'message'     => $this->alreadyProcessedMessage($existingLog->scan_status),
                'status_code' => 422,
            ];
        }

        return [
            'authorized'      => true,
            'detail'          => $detail,
            'shift_start_at'  => $shiftStartAt,
            'shift_end_at'    => $shiftEndAt,
            'round'           => $round,
            'active_handover' => $activeHandover,
        ];
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

    // Sertakan shift Malam dari kemarin agar satpam yang bertugas dini hari
    // tetap bisa melihat jadwal shift-nya.
    $scheduleDetails = ScheduleDetail::with([
        'schedule.katim.user',
        'satpam.user',
        'patrolPoint:id,name,location_address',
    ])
        ->where(function ($query) use ($satpam) {
            $query->where('satpam_id', $satpam->id)
                  ->orWhereHas('schedule', fn ($sq) => $sq->where('katim_id', $satpam->id));
        })
        ->whereHas('schedule', function ($query) use ($date) {
            $query->where('status', 'aktif')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date->copy()->subDay());
        })
        ->orderBy('sequence_order')
        ->get();

    if ($scheduleDetails->isEmpty()) {
        return response()->json([
            'message' => 'Jadwal patroli berhasil diambil.',
            'date'    => $date->toDateString(),
            'rounds'  => [],
        ]);
    }

    $firstDetail = $scheduleDetails->first();
    $shiftLabel  = $firstDetail->shift_label;
    $shiftStart  = substr($firstDetail->shift_start, 0, 5);
    $shiftEnd    = substr($firstDetail->shift_end, 0, 5);

    // Ambil semua log dari schedule detail yang berlaku, dikelompokkan
    // berdasarkan pasangan (schedule_detail_id, patrol_round).
    $scheduleDetailIds = $scheduleDetails->pluck('id');

    // Ambil semua log yang terkait dengan schedule detail satpam ini,
    // termasuk jika discan oleh satpam lain via handover.
    $allLogs = PatrolLog::with('satpam.user')
        ->whereIn('schedule_detail_id', $scheduleDetailIds)
        ->orderBy('scan_time')
        ->get()
        ->groupBy(fn ($log) => $log->schedule_detail_id . '-' . ($log->patrol_round ?? 0));

    // Ambil handover yang diajukan oleh satpam ini (sent)
    $handoversSent = PatrolHandover::with('toSatpam.user')
        ->where('from_satpam_id', $satpam->id)
        ->whereDate('handover_date', $date)
        ->whereIn('status', ['pending', 'accepted'])
        ->get()
        ->keyBy(fn ($h) => $h->schedule_detail_id . '-' . $h->patrol_round);

    // Ambil handover yang diterima oleh satpam ini dan sudah accepted (received)
    $handoversReceived = PatrolHandover::with(['fromSatpam.user', 'patrolPoint', 'scheduleDetail'])
        ->where('to_satpam_id', $satpam->id)
        ->whereDate('handover_date', $date)
        ->where('status', 'accepted')
        ->get()
        ->groupBy('patrol_round');

    $roundService = new PatrolRoundService();
    $roundTimes   = $roundService->getRoundTimes($shiftLabel);

    // Bangun response berdasarkan 4 putaran.
    // Masing-masing petugas hanya menerima putaran yang menjadi tugasnya (atau titik handover yang diterima).
    $rounds = collect($roundTimes)
        ->filter(function ($targetTime, $roundNumber) use ($scheduleDetails, $handoversReceived, $satpam, $roundService) {
            $firstDetail = $scheduleDetails->first();
            if (! $firstDetail) {
                return false;
            }

            $isAssigned = $roundService->isRoundAssignedTo($firstDetail, $roundNumber, $satpam->id);
            $hasReceivedHandover = $handoversReceived->has($roundNumber);

            return $isAssigned || $hasReceivedHandover;
        })
        ->map(function ($targetTime, $roundNumber) use (
            $scheduleDetails, $allLogs, $handoversSent, $handoversReceived, $satpam, $roundService
        ) {
            $firstDetail = $scheduleDetails->first();
            $assignedInfo = $firstDetail ? $roundService->getAssignedSatpamInfo($firstDetail, $roundNumber) : null;
            $isMyRound = $firstDetail ? $roundService->isRoundAssignedTo($firstDetail, $roundNumber, $satpam->id) : true;

            $myPoints = $isMyRound
                ? $scheduleDetails->map(function (ScheduleDetail $detail) use ($roundNumber, $allLogs, $handoversSent, $satpam) {
                    $key       = $detail->id . '-' . $roundNumber;
                    $pointLogs = $allLogs->get($key, collect());

                    // Prioritaskan log final; fallback ke log terakhir (misalnya anomali).
                    $log = $pointLogs->first(fn ($l) => in_array($l->scan_status, self::FINAL_SCAN_STATUSES))
                        ?? $pointLogs->last();

                    $handoverSent = $handoversSent->get($detail->id . '-' . $roundNumber);
                    $handoverInfo = null;

                    if ($handoverSent) {
                        $handoverInfo = [
                            'id'           => $handoverSent->id,
                            'status'       => $handoverSent->status,
                            'partner_name' => $handoverSent->toSatpam?->user?->name ?? 'Rekan Satpam',
                            'reason'       => $handoverSent->reason,
                            'is_sender'    => true,
                        ];
                    }

                    // Keterangan jika titik ini diselesaikan oleh satpam lain via handover
                    $scannedByPartner = ($log && $log->satpam_id !== $satpam->id)
                        ? ($log->satpam?->user?->name ?? 'Rekan Satpam')
                        : null;

                    $status = $log?->scan_status;
                    $statusLabel = null;

                    if ($log) {
                        $baseLabel = $this->scanStatusLabel($log->scan_status);
                        if ($log->delegated_from_satpam_id !== null || ($handoverSent && $handoverSent->status === 'accepted')) {
                            $statusLabel = $baseLabel . ' - Handover';
                        } else {
                            $statusLabel = $baseLabel;
                        }
                    } elseif ($handoverSent && $handoverSent->status === 'accepted') {
                        $statusLabel = 'Dialihkan (Handover)';
                    } elseif ($handoverSent && $handoverSent->status === 'pending') {
                        $statusLabel = 'Menunggu Konfirmasi Handover';
                    }

                    return [
                        'schedule_detail_id' => $detail->id,
                        'sequence_order'     => $detail->sequence_order,
                        'patrol_point_id'    => $detail->patrol_point_id,
                        'patrol_point_name'  => $detail->patrolPoint?->name ?? '-',
                        'location_address'   => $detail->patrolPoint?->location_address,
                        'scan_status'        => $status,
                        'scan_status_label'  => $statusLabel,
                        'scan_time'          => $log?->scan_time,
                        'patrol_log_id'      => $log?->id,
                        'scanned_by_partner' => $scannedByPartner,
                        'handover'           => $handoverInfo,
                    ];
                })
                : collect();

            // Tambahkan titik dari satpam lain yang berhasil di-handover ke satpam ini (accepted)
            $receivedPoints = ($handoversReceived->get($roundNumber) ?? collect())->map(function ($h) use ($roundNumber, $satpam) {
                $log = PatrolLog::where('patrol_handover_id', $h->id)
                    ->orWhere(function ($q) use ($h, $roundNumber) {
                        $q->where('schedule_detail_id', $h->schedule_detail_id)
                          ->where('patrol_round', $roundNumber);
                    })
                    ->first();

                $status = $log?->scan_status;
                $statusLabel = null;

                if ($log) {
                    $statusLabel = $this->scanStatusLabel($log->scan_status) . ' - Handover';
                } else {
                    $statusLabel = 'Handover Masuk';
                }

                return [
                    'schedule_detail_id' => $h->schedule_detail_id,
                    'sequence_order'     => $h->scheduleDetail?->sequence_order ?? 99,
                    'patrol_point_id'    => $h->patrol_point_id,
                    'patrol_point_name'  => $h->patrolPoint?->name ?? '-',
                    'location_address'   => $h->patrolPoint?->location_address,
                    'scan_status'        => $status,
                    'scan_status_label'  => $statusLabel,
                    'scan_time'          => $log?->scan_time,
                    'patrol_log_id'      => $log?->id,
                    'scanned_by_partner' => null,
                    'handover'           => [
                        'id'           => $h->id,
                        'status'       => 'accepted',
                        'partner_name' => $h->fromSatpam?->user?->name ?? 'Rekan Satpam',
                        'reason'       => $h->reason,
                        'is_sender'    => false,
                    ],
                ];
            });

            $points = $myPoints->concat($receivedPoints)->unique('patrol_point_id')->sortBy('sequence_order')->values();

            return [
                'round'                => $roundNumber,
                'target_time'          => $targetTime,
                'assigned_satpam_id'   => $assignedInfo['id'] ?? null,
                'assigned_satpam_name' => $assignedInfo['name'] ?? null,
                'assigned_role'        => $assignedInfo['role'] ?? 'satpam',
                'points'               => $points,
            ];
        })->values();

    $legacySchedule = $scheduleDetails->map(function (ScheduleDetail $detail) use ($allLogs) {
        $pointLogs = $allLogs->get($detail->id . '-1', collect());
        $log = $pointLogs->first(fn ($l) => in_array($l->scan_status, self::FINAL_SCAN_STATUSES))
            ?? $pointLogs->last();

        return [
            'id'                => $detail->id,
            'schedule_id'       => $detail->schedule_id,
            'shift_start'       => $detail->shift_start,
            'shift_end'         => $detail->shift_end,
            'shift_label'       => $detail->shift_label,
            'sequence_order'    => $detail->sequence_order,
            'patrol_point'      => [
                'id'               => $detail->patrolPoint?->id,
                'name'             => $detail->patrolPoint?->name,
                'location_address' => $detail->patrolPoint?->location_address,
            ],
            'scan_status'       => $log?->scan_status,
            'scan_status_label' => $log ? $this->scanStatusLabel($log->scan_status) : null,
            'scan_time'         => $log?->scan_time,
            'schedule'          => [
                'id'    => $detail->schedule?->id,
                'title' => $detail->schedule?->title,
            ],
        ];
    })->values();

    return response()->json([
        'message'     => 'Jadwal patroli berhasil diambil.',
        'date'        => $date->toDateString(),
        'shift_label' => $shiftLabel,
        'shift_start' => $shiftStart,
        'shift_end'   => $shiftEnd,
        'rounds'      => $rounds,
        'schedule'    => $legacySchedule,
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
    // Titik yang boleh di-skip: titik yang ada di jadwal satpam shift ini,
    // dan belum punya log final (berhasil/terlambat/skip) pada putaran saat ini.
    // Anomali tidak dihitung final — titiknya tetap ditawarkan untuk di-skip.
    public function skipOptions(Request $request)
    {
        $satpam = Satpam::where('user_id', $request->user()->id)->first();

        if (! $satpam) {
            return response()->json([
                'message' => 'Data satpam tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $scheduleDetails = ScheduleDetail::with(['patrolPoint', 'schedule.katim.user', 'satpam.user'])
            ->where(function ($q) use ($satpam) {
                $q->where('satpam_id', $satpam->id)
                  ->orWhereHas('schedule', fn ($sq) => $sq->where('katim_id', $satpam->id));
            })
            ->whereHas('schedule', function ($query) {
                $query->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today()->copy()->subDay());
            })
            ->orderBy('sequence_order')
            ->get();

        // Tentukan putaran aktif saat ini (default 1 jika shift belum dimulai).
        $currentRound = 1;
        $firstDetail  = $scheduleDetails->first();
        $roundService = new PatrolRoundService();

        if ($firstDetail) {
            foreach ([today(), today()->copy()->subDay()] as $anchor) {
                [$windowStart, $windowEnd] = $roundService->shiftWindow(
                    $firstDetail->shift_start,
                    $firstDetail->shift_end,
                    $anchor
                );

                if (now()->between($windowStart, $windowEnd)) {
                    $currentRound = $roundService->resolveRound(
                        $firstDetail->shift_label,
                        $anchor,
                        now()
                    );
                    break;
                }
            }
        }

        // Titik dianggap sudah selesai pada putaran ini jika punya log final
        // (berhasil/terlambat/skip) dengan patrol_round yang sama.
        $scheduleDetailIds  = $scheduleDetails->pluck('id');
        $processedDetailIds = PatrolLog::whereIn('schedule_detail_id', $scheduleDetailIds)
            ->where('patrol_round', $currentRound)
            ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
            ->pluck('schedule_detail_id')
            ->unique();

        // Titik yang sudah dialihkan (handover accepted) oleh satpam ini ke rekan
        $handedOverDetailIds = PatrolHandover::where('from_satpam_id', $satpam->id)
            ->whereIn('schedule_detail_id', $scheduleDetailIds)
            ->where('patrol_round', $currentRound)
            ->where('status', 'accepted')
            ->whereDate('handover_date', today())
            ->pluck('schedule_detail_id')
            ->unique();

        $excludeDetailIds = $processedDetailIds->concat($handedOverDetailIds)->unique();

        $isMyRound = $firstDetail ? $roundService->isRoundAssignedTo($firstDetail, $currentRound, $satpam->id) : true;

        $myRemainingPoints = $isMyRound
            ? $scheduleDetails
                ->whereNotIn('id', $excludeDetailIds)
                ->unique('patrol_point_id')
                ->sortBy('sequence_order')
                ->map(fn (ScheduleDetail $detail) => [
                    'patrol_point_id' => $detail->patrol_point_id,
                    'name'            => $detail->patrolPoint?->name ?? '-',
                    'sequence_order'  => $detail->sequence_order,
                    'current_round'   => $currentRound,
                ])
            : collect();

        // Titik yang diterima lewat handover dan belum discan
        $receivedHandovers = PatrolHandover::with(['patrolPoint', 'scheduleDetail'])
            ->where('to_satpam_id', $satpam->id)
            ->where('patrol_round', $currentRound)
            ->where('status', 'accepted')
            ->whereDate('handover_date', today())
            ->get();

        $receivedPoints = $receivedHandovers->filter(function ($h) use ($currentRound) {
            return ! PatrolLog::where('patrol_handover_id', $h->id)
                ->where('patrol_round', $currentRound)
                ->whereIn('scan_status', self::FINAL_SCAN_STATUSES)
                ->exists();
        })->map(fn ($h) => [
            'patrol_point_id' => $h->patrol_point_id,
            'name'            => ($h->patrolPoint?->name ?? '-') . ' (Handover)',
            'sequence_order'  => $h->scheduleDetail?->sequence_order ?? 99,
            'current_round'   => $currentRound,
        ]);

        $points = $myRemainingPoints->concat($receivedPoints)->sortBy('sequence_order')->values();

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

        $context = $this->resolvePatrolContext($satpam, $patrolPoint);

        if (! $context['authorized']) {
            return response()->json([
                'message' => $context['message'],
            ], $context['status_code'] ?? 422);
        }

        [$scheduleDetail, $shiftStartAt, $shiftEndAt] = [$context['detail'], $context['shift_start_at'], $context['shift_end_at']];
        $round = $context['round'];
        $activeHandover = $context['active_handover'];

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

        if ($activeHandover) {
            $partnerName = $activeHandover->fromSatpam?->user?->name ?? 'Rekan';
            $handoverNote = "Handover dari {$partnerName} ({$activeHandover->reason})";
            $note = $note ? "{$note} | {$handoverNote}" : $handoverNote;
        }

        $patrolLog = PatrolLog::create([
            'satpam_id'                => $satpam->id,
            'delegated_from_satpam_id' => $activeHandover ? $activeHandover->from_satpam_id : null,
            'patrol_handover_id'       => $activeHandover ? $activeHandover->id : null,
            'patrol_point_id'          => $patrolPoint->id,
            'schedule_detail_id'       => $scheduleDetail?->id,
            'scan_time'                => now(),
            'latitude'                 => $validated['latitude'] ?? $patrolPoint->latitude,
            'longitude'                => $validated['longitude'] ?? $patrolPoint->longitude,
            'distance_from_point'      => $distance,
            'patrol_round'             => $round,
            'scan_status'              => $scanStatus,
            'note'                     => $note,
        ]);

        $skippedPoints = $activeHandover
            ? []
            : $this->markEarlierCheckpointsAsMissed($satpam, $scheduleDetail, $round);

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

        $context = $this->resolvePatrolContext($satpam, $patrolPoint);

        if (! $context['authorized']) {
            return response()->json([
                'message' => $context['message'],
            ], $context['status_code'] ?? 422);
        }

        $scheduleDetail = $context['detail'];
        $round          = $context['round'];
        $activeHandover = $context['active_handover'];

        $note = $validated['reason'];
        if ($activeHandover) {
            $partnerName = $activeHandover->fromSatpam?->user?->name ?? 'Rekan';
            $note = "{$note} | Handover dari {$partnerName}";
        }

        $patrolLog = PatrolLog::create([
            'satpam_id'                => $satpam->id,
            'delegated_from_satpam_id' => $activeHandover ? $activeHandover->from_satpam_id : null,
            'patrol_handover_id'       => $activeHandover ? $activeHandover->id : null,
            'patrol_point_id'          => $patrolPoint->id,
            'schedule_detail_id'       => $scheduleDetail?->id,
            'scan_time'                => now(),
            'latitude'                 => $patrolPoint->latitude,
            'longitude'                => $patrolPoint->longitude,
            'patrol_round'             => $round,
            'scan_status'              => 'skip',
            'note'                     => $note,
            'review_status'            => 'pending',
        ]);

        SkipReason::create([
            'patrol_log_id' => $patrolLog->id,
            'reason'        => $validated['reason'],
        ]);

        $skippedPoints = $activeHandover
            ? []
            : $this->markEarlierCheckpointsAsMissed($satpam, $scheduleDetail, $round);

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
