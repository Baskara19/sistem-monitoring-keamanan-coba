<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ScheduleDetail;

/**
 * PatrolRoundService
 *
 * Satu sumber tunggal untuk seluruh logika putaran (round) patroli.
 *
 * Aturan utama:
 *   - 1 shift = 4 putaran, rute yang sama diulang 4 kali.
 *   - Putaran TIDAK disimpan di schedule_details, melainkan di patrol_logs.patrol_round.
 *   - Shift Malam (22:00–06:00) bersifat overnight: putaran 1–4 jatuh di hari
 *     berikutnya, tetapi tetap merupakan bagian dari shift yang dimulai kemarin.
 *
 * Jam target putaran:
 *   Pagi  : P1=09:00  P2=11:00  P3=13:00  P4=14:00
 *   Siang : P1=16:00  P2=18:00  P3=20:00  P4=22:00
 *   Malam : P1=00:00  P2=02:00  P3=04:00  P4=06:00
 *
 * Penggunaan:
 *   $service = new PatrolRoundService();
 *   $round   = $service->resolveRound($detail->shift_label, $shiftAnchor, now());
 */
class PatrolRoundService
{
    /**
     * Jam target (HH:MM) untuk setiap putaran per label shift.
     *
     * Kunci luar : label shift ('Pagi' | 'Siang' | 'Malam')
     * Kunci dalam: nomor putaran (1–4)
     */
    private const ROUND_TIMES = [
        'Pagi'  => [1 => '09:00', 2 => '11:00', 3 => '13:00', 4 => '14:00'],
        'Siang' => [1 => '16:00', 2 => '18:00', 3 => '20:00', 4 => '22:00'],
        'Malam' => [1 => '00:00', 2 => '02:00', 3 => '04:00', 4 => '06:00'],
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Ambil semua jam putaran untuk satu shift.
     *
     * @param  string $shiftLabel  'Pagi' | 'Siang' | 'Malam'
     * @return array<int, string>  [ 1 => '09:00', 2 => '11:00', ... ]
     */
    public function getRoundTimes(string $shiftLabel): array
    {
        return self::ROUND_TIMES[$shiftLabel] ?? [];
    }

    /**
     * Ambil jam target (string "HH:MM") untuk putaran tertentu.
     *
     * @param  string   $shiftLabel  'Pagi' | 'Siang' | 'Malam'
     * @param  int      $round       1–4
     * @return string|null           null jika shift/round tidak dikenal
     */
    public function roundTargetTime(string $shiftLabel, int $round): ?string
    {
        return self::ROUND_TIMES[$shiftLabel][$round] ?? null;
    }

    /**
     * Tentukan putaran mana yang sedang berjalan berdasarkan waktu scan.
     *
     * Logika:
     *   Temukan jam putaran terbesar yang <= $scanTime.
     *   Contoh Pagi: scan jam 10:30 → round 1 (jam 09:00 sudah lewat, 11:00 belum).
     *
     * @param  string  $shiftLabel   'Pagi' | 'Siang' | 'Malam'
     * @param  Carbon  $shiftAnchor  Tanggal shift MULAI (bukan tanggal scan).
     *                               Untuk Malam 22:00 Senin → anchor = Senin.
     *                               Untuk Pagi/Siang → anchor = tanggal shift itu sendiri.
     * @param  Carbon  $scanTime     Waktu scan (biasanya now()).
     * @return int                   Nomor putaran (1–4). Default 1 jika tidak bisa ditentukan.
     */
    public function resolveRound(string $shiftLabel, Carbon $shiftAnchor, Carbon $scanTime): int
    {
        $roundTimes = self::ROUND_TIMES[$shiftLabel] ?? [];

        if (empty($roundTimes)) {
            return 1;
        }

        // Shift Malam: putaran 1–4 jatuh di hari BERIKUTNYA dari anchor.
        // Pagi/Siang: putaran jatuh di hari yang sama dengan anchor.
        $roundDay = ($shiftLabel === 'Malam')
            ? $shiftAnchor->copy()->addDay()
            : $shiftAnchor->copy();

        // Build Carbon timestamp untuk setiap jam target putaran.
        $roundStarts = [];
        foreach ($roundTimes as $roundNumber => $timeString) {
            $roundStarts[$roundNumber] = Carbon::parse(
                $roundDay->toDateString() . ' ' . $timeString
            );
        }

        // Putaran yang berlaku = putaran terbesar yang jam targetnya sudah terlewati.
        // Kalau belum lewat satupun (scan terlalu awal), kembalikan putaran 1.
        $resolved = 1;
        foreach ($roundStarts as $roundNumber => $roundStart) {
            if ($scanTime->greaterThanOrEqualTo($roundStart)) {
                $resolved = $roundNumber;
            }
        }

        return $resolved;
    }

    /**
     * Tentukan label shift ('Pagi' | 'Siang' | 'Malam') dari jam mulai shift.
     *
     * Pagi  : 06:00 – 13:59
     * Siang : 14:00 – 21:59
     * Malam : 22:00 – 05:59 (lintas tengah malam)
     *
     * @param  string  $shiftStart  Format "HH:MM" atau "HH:MM:SS"
     * @return string
     */
    public function shiftLabel(string $shiftStart): string
    {
        $hour = (int) substr($shiftStart, 0, 2);

        if ($hour >= 22 || $hour < 6) {
            return 'Malam';
        }

        return $hour < 14 ? 'Pagi' : 'Siang';
    }

    /**
     * Hitung jendela waktu [Carbon $start, Carbon $end] untuk sebuah shift
     * pada tanggal anchor tertentu.
     *
     * Untuk shift Malam (22:00–06:00), jam selesai otomatis digeser +1 hari
     * sehingga jendela waktu tepat mencakup tengah malam.
     *
     * @param  string  $shiftStart  Format "HH:MM" atau "HH:MM:SS"
     * @param  string  $shiftEnd    Format "HH:MM" atau "HH:MM:SS"
     * @param  Carbon  $anchor      Tanggal shift dimulai.
     * @return array{0: Carbon, 1: Carbon}  [$windowStart, $windowEnd]
     */
    public function shiftWindow(string $shiftStart, string $shiftEnd, Carbon $anchor): array
    {
        $start = Carbon::parse($anchor->toDateString() . ' ' . $shiftStart);
        $end   = Carbon::parse($anchor->toDateString() . ' ' . $shiftEnd);

        // Shift lintas tengah malam: end < start → tambah 1 hari ke end.
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * Ambil Carbon timestamp untuk jam target putaran tertentu, disesuaikan
     * dengan anchor shift.
     *
     * Berguna untuk menghitung apakah scan terlambat dari target putaran.
     *
     * @param  string  $shiftLabel   'Pagi' | 'Siang' | 'Malam'
     * @param  int     $round        1–4
     * @param  Carbon  $shiftAnchor  Tanggal shift mulai.
     * @return Carbon|null
     */
    public function roundTargetCarbon(string $shiftLabel, int $round, Carbon $shiftAnchor): ?Carbon
    {
        $timeString = self::ROUND_TIMES[$shiftLabel][$round] ?? null;

        if ($timeString === null) {
            return null;
        }

        $roundDay = ($shiftLabel === 'Malam')
            ? $shiftAnchor->copy()->addDay()
            : $shiftAnchor->copy();

        return Carbon::parse($roundDay->toDateString() . ' ' . $timeString);
    }

    /**
     * Hitung daftar anchor yang perlu dicoba saat menentukan shift aktif.
     *
     * Untuk menangani shift Malam (22:00–06:00), kita perlu mencoba hari ini
     * DAN kemarin sebagai anchor, lalu pilih yang jendela waktunya mencakup
     * $atTime.
     *
     * @return Carbon[]  [today(), today()->subDay()]
     */
    public function anchorCandidates(): array
    {
        return [today(), today()->copy()->subDay()];
    }

    /**
     * Tentukan ID satpam yang bertanggung jawab atas putaran tertentu.
     * Jika jadwal memiliki pembagian KAT (katim_id tidak null):
     * - Putaran ganjil (1, 3): Satpam pelaksana ($detail->satpam_id)
     * - Putaran genap (2, 4): KAT / Katim ($detail->schedule->katim_id)
     * Jika tidak ada KAT (jadwal mandiri):
     * - Semua putaran (1, 2, 3, 4): Satpam pelaksana ($detail->satpam_id)
     *
     * @param  ScheduleDetail  $detail
     * @param  int             $round (1–4)
     * @return int|null
     */
    public function getAssignedSatpamId(ScheduleDetail $detail, int $round): ?int
    {
        $katimId = $detail->schedule?->katim_id;

        if ($katimId) {
            // Pola selang-seling 2:2:
            // Ganjil (1, 3) = Satpam
            // Genap (2, 4)  = KAT
            return ($round % 2 === 1) ? $detail->satpam_id : $katimId;
        }

        return $detail->satpam_id;
    }

    /**
     * Cek apakah putaran ini ditugaskan ke petugas tertentu.
     */
    public function isRoundAssignedTo(ScheduleDetail $detail, int $round, int $satpamId): bool
    {
        return $this->getAssignedSatpamId($detail, $round) === $satpamId;
    }

    /**
     * Dapatkan nama dan role penanggung jawab putaran tertentu.
     */
    public function getAssignedSatpamInfo(ScheduleDetail $detail, int $round): array
    {
        $katimId = $detail->schedule?->katim_id;

        if ($katimId) {
            if ($round % 2 === 1) {
                return [
                    'id'   => $detail->satpam_id,
                    'name' => $detail->satpam?->user?->name ?? 'Satpam',
                    'role' => 'satpam',
                ];
            } else {
                $katim = $detail->schedule?->katim;
                return [
                    'id'   => $katimId,
                    'name' => $katim?->user?->name ?? 'KAT',
                    'role' => 'katim',
                ];
            }
        }

        return [
            'id'   => $detail->satpam_id,
            'name' => $detail->satpam?->user?->name ?? 'Satpam',
            'role' => 'satpam',
        ];
    }
}

