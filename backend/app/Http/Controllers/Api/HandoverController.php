<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatrolHandover;
use App\Models\PatrolLog;
use App\Models\PatrolPoint;
use App\Models\Satpam;
use App\Models\ScheduleDetail;
use App\Services\PatrolRoundService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HandoverController extends Controller
{
    /**
     * Dapatkan satpam yang sedang login.
     */
    private function currentSatpam(Request $request): ?Satpam
    {
        return Satpam::with('user')->where('user_id', $request->user()->id)->first();
    }

    /**
     * Ambil rekan satpam yang bertugas di shift & lokasi yang sama hari ini.
     * GET /api/satpam/handovers/colleagues
     */
    public function colleagues(Request $request)
    {
        $satpam = $this->currentSatpam($request);

        if (! $satpam) {
            return response()->json(['message' => 'Data satpam tidak ditemukan.'], 404);
        }

        // Ambil jadwal aktif satpam yang sedang login untuk mengetahui shift_label & location_id
        $mySchedule = ScheduleDetail::where('satpam_id', $satpam->id)
            ->whereHas('schedule', function ($q) {
                $q->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today()->copy()->subDay());
            })
            ->first();

        if (! $mySchedule) {
            return response()->json([
                'colleagues' => [],
                'message'    => 'Anda tidak memiliki jadwal aktif hari ini.',
            ]);
        }

        $myShiftLabel = $mySchedule->shift_label;
        $locationId   = $satpam->user?->location_id ?? $request->user()->location_id;

        // Cari rekan satpam yang memiliki jadwal aktif hari ini di lokasi yang sama dan shift yang sama
        $colleagues = Satpam::with(['user', 'scheduleDetails.schedule'])
            ->where('id', '!=', $satpam->id)
            ->whereHas('user', function ($q) use ($locationId) {
                if ($locationId) {
                    $q->where('location_id', $locationId);
                }
            })
            ->whereHas('scheduleDetails.schedule', function ($q) {
                $q->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today()->copy()->subDay());
            })
            ->get()
            ->filter(function ($s) use ($myShiftLabel) {
                // Cocokkan shift_label (yang merupakan accessor model)
                return $s->scheduleDetails->contains(function ($sd) use ($myShiftLabel) {
                    $sch = $sd->schedule;
                    if (! $sch || $sch->status !== 'aktif') {
                        return false;
                    }
                    $today = today();
                    $startDate = Carbon::parse($sch->start_date)->startOfDay();
                    $endDate   = Carbon::parse($sch->end_date)->endOfDay();
                    if ($today->lt($startDate) || $today->gt($endDate->copy()->addDay())) {
                        return false;
                    }

                    return $sd->shift_label === $myShiftLabel;
                });
            })
            ->map(function ($s) {
                return [
                    'id'            => $s->id,
                    'name'          => $s->user?->name ?? 'Satpam',
                    'nipkwt'        => $s->user?->nipkwt ?? '-',
                    'badge_number'  => $s->badge_number ?? '-',
                ];
            })
            ->values();

        return response()->json([
            'colleagues' => $colleagues,
        ]);
    }

    /**
     * Ajukan handover untuk satu titik patroli pada putaran tertentu.
     * POST /api/satpam/handovers/request
     */
    public function requestHandover(Request $request)
    {
        $satpam = $this->currentSatpam($request);

        if (! $satpam) {
            return response()->json(['message' => 'Data satpam tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'patrol_point_id' => 'required|exists:patrol_points,id',
            'to_satpam_id'    => 'required|exists:satpams,id|different:' . $satpam->id,
            'patrol_round'    => 'required|integer|min:1|max:4',
            'reason'          => 'required|string|max:255',
        ], [
            'to_satpam_id.different' => 'Anda tidak bisa melakukan handover ke diri sendiri.',
            'reason.required'        => 'Alasan handover wajib diisi.',
        ]);

        // Cek apakah titik tersebut ada di jadwal satpam pemohon hari ini
        $myDetail = ScheduleDetail::where('satpam_id', $satpam->id)
            ->where('patrol_point_id', $validated['patrol_point_id'])
            ->whereHas('schedule', function ($q) {
                $q->where('status', 'aktif')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today()->copy()->subDay());
            })
            ->first();

        if (! $myDetail) {
            return response()->json([
                'message' => 'Titik ini bukan bagian dari jadwal Anda hari ini.',
            ], 422);
        }

        $todayDate = today()->toDateString();

        // Cek apakah titik tersebut sudah selesai discan oleh pemohon pada putaran ini
        $alreadyScanned = PatrolLog::where('schedule_detail_id', $myDetail->id)
            ->where('patrol_round', $validated['patrol_round'])
            ->whereIn('scan_status', ['berhasil', 'terlambat', 'skip'])
            ->exists();

        if ($alreadyScanned) {
            return response()->json([
                'message' => 'Titik patroli ini sudah selesai discan pada putaran ini, tidak bisa di-handover.',
            ], 422);
        }

        // Cek apakah sudah ada request handover pending untuk titik & putaran ini
        $existingHandover = PatrolHandover::where('from_satpam_id', $satpam->id)
            ->where('schedule_detail_id', $myDetail->id)
            ->where('patrol_round', $validated['patrol_round'])
            ->whereDate('handover_date', $todayDate)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingHandover) {
            if ($existingHandover->status === 'accepted') {
                return response()->json([
                    'message' => 'Titik ini sudah berhasil di-handover ke rekan satpam.',
                ], 422);
            }

            return response()->json([
                'message' => 'Sudah ada permintaan handover yang sedang menunggu persetujuan rekan untuk titik ini.',
            ], 422);
        }

        $handover = PatrolHandover::create([
            'from_satpam_id'     => $satpam->id,
            'to_satpam_id'       => $validated['to_satpam_id'],
            'schedule_detail_id' => $myDetail->id,
            'patrol_point_id'    => $validated['patrol_point_id'],
            'patrol_round'       => $validated['patrol_round'],
            'handover_date'      => $todayDate,
            'reason'             => $validated['reason'],
            'status'             => 'pending',
        ]);

        return response()->json([
            'message'  => 'Permintaan handover berhasil dikirim. Menunggu konfirmasi rekan satpam.',
            'handover' => $handover->load(['toSatpam.user', 'patrolPoint']),
        ], 201);
    }

    /**
     * Ambil daftar permintaan handover yang masuk ke satpam ini (perlu konfirmasi).
     * GET /api/satpam/handovers/pending
     */
    public function pendingHandovers(Request $request)
    {
        $satpam = $this->currentSatpam($request);

        if (! $satpam) {
            return response()->json(['message' => 'Data satpam tidak ditemukan.'], 404);
        }

        $pending = PatrolHandover::with(['fromSatpam.user', 'patrolPoint', 'scheduleDetail'])
            ->where('to_satpam_id', $satpam->id)
            ->where('status', 'pending')
            ->whereDate('handover_date', '>=', today()->copy()->subDay())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'id'                 => $h->id,
                    'from_satpam_id'     => $h->from_satpam_id,
                    'from_satpam_name'   => $h->fromSatpam?->user?->name ?? 'Rekan Satpam',
                    'from_satpam_nipkwt' => $h->fromSatpam?->user?->nipkwt ?? '-',
                    'patrol_point_id'    => $h->patrol_point_id,
                    'patrol_point_name'  => $h->patrolPoint?->name ?? 'Titik Patroli',
                    'patrol_round'       => $h->patrol_round,
                    'handover_date'      => $h->handover_date?->toDateString(),
                    'reason'             => $h->reason,
                    'created_at'         => $h->created_at?->format('H:i'),
                ];
            });

        return response()->json($pending);
    }

    /**
     * Respon permintaan handover (Terima / Tolak).
     * POST /api/satpam/handovers/{id}/respond
     */
    public function respondHandover(Request $request, $id)
    {
        $satpam = $this->currentSatpam($request);

        if (! $satpam) {
            return response()->json(['message' => 'Data satpam tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
        ]);

        $handover = PatrolHandover::where('id', $id)
            ->where('to_satpam_id', $satpam->id)
            ->first();

        if (! $handover) {
            return response()->json([
                'message' => 'Permintaan handover tidak ditemukan.',
            ], 404);
        }

        if ($handover->status !== 'pending') {
            return response()->json([
                'message' => 'Permintaan handover ini sudah pernah direspon sebelumnya (' . $handover->status . ').',
            ], 422);
        }

        $newStatus = $validated['action'] === 'accept' ? 'accepted' : 'rejected';

        $handover->update([
            'status'       => $newStatus,
            'responded_at' => now(),
        ]);

        $message = $newStatus === 'accepted'
            ? 'Permintaan handover berhasil diterima. Anda sekarang dapat men-scan titik ini.'
            : 'Permintaan handover telah ditolak.';

        return response()->json([
            'message'  => $message,
            'status'   => $newStatus,
            'handover' => $handover,
        ]);
    }

    /**
     * Batalkan pengajuan handover oleh pemohon (hanya jika masih pending).
     * POST /api/satpam/handovers/{id}/cancel
     */
    public function cancelHandover(Request $request, $id)
    {
        $satpam = $this->currentSatpam($request);

        if (! $satpam) {
            return response()->json(['message' => 'Data satpam tidak ditemukan.'], 404);
        }

        $handover = PatrolHandover::where('id', $id)
            ->where('from_satpam_id', $satpam->id)
            ->first();

        if (! $handover) {
            return response()->json(['message' => 'Permintaan handover tidak ditemukan.'], 404);
        }

        if ($handover->status !== 'pending') {
            return response()->json([
                'message' => 'Permintaan tidak bisa dibatalkan karena sudah direspon (' . $handover->status . ').',
            ], 422);
        }

        $handover->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Permintaan handover berhasil dibatalkan.',
        ]);
    }

    /**
     * Ambil riwayat permintaan handover yang diajukan oleh satpam ini.
     * GET /api/satpam/handovers/my-requests
     */
    public function myRequests(Request $request)
    {
        $satpam = $this->currentSatpam($request);

        if (! $satpam) {
            return response()->json(['message' => 'Data satpam tidak ditemukan.'], 404);
        }

        $requests = PatrolHandover::with(['toSatpam.user', 'patrolPoint'])
            ->where('from_satpam_id', $satpam->id)
            ->whereDate('handover_date', '>=', today()->copy()->subDay())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'id'                => $h->id,
                    'to_satpam_id'      => $h->to_satpam_id,
                    'to_satpam_name'    => $h->toSatpam?->user?->name ?? 'Rekan Satpam',
                    'patrol_point_id'   => $h->patrol_point_id,
                    'patrol_point_name' => $h->patrolPoint?->name ?? 'Titik Patroli',
                    'patrol_round'      => $h->patrol_round,
                    'reason'            => $h->reason,
                    'status'            => $h->status,
                    'created_at'        => $h->created_at?->format('H:i'),
                ];
            });

        return response()->json([
            'my_handovers' => $requests,
        ]);
    }
}
