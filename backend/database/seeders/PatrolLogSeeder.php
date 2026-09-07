<?php

namespace Database\Seeders;

use App\Models\PatrolLog;
use App\Models\PatrolPoint;
use App\Models\Satpam;
use Illuminate\Database\Seeder;

class PatrolLogSeeder extends Seeder
{
    /**
     * Seed sample patrol activity logs for the admin activities page.
     */
    public function run(): void
    {
        $satpam = Satpam::first();
        $patrolPoint = PatrolPoint::first();

        if (! $satpam || ! $patrolPoint) {
            $this->command->warn('Lewati PatrolLogSeeder: butuh minimal 1 data satpam dan 1 titik patroli.');

            return;
        }

        $samples = [
            ['scan_status' => 'berhasil', 'note' => 'Scan tepat waktu', 'minutes_ago' => 45],
            ['scan_status' => 'terlambat', 'note' => 'Terlambat 15 menit dari jadwal', 'minutes_ago' => 120],
            ['scan_status' => 'skip', 'note' => 'Dilewati, sedang menangani insiden lain', 'minutes_ago' => 200],
            ['scan_status' => 'anomali', 'note' => 'Jarak scan di luar radius titik patroli', 'minutes_ago' => 300],
            ['scan_status' => 'berhasil', 'note' => 'Scan tepat waktu', 'minutes_ago' => 24 * 60 + 30],
            ['scan_status' => 'anomali', 'note' => 'Titik sebelumnya terlewat, baru tercatat di titik berikutnya', 'minutes_ago' => 24 * 60 + 90],
            ['scan_status' => 'terlambat', 'note' => 'Terlambat 8 menit dari jadwal', 'minutes_ago' => 24 * 60 + 200],
            ['scan_status' => 'berhasil', 'note' => 'Scan tepat waktu', 'minutes_ago' => 24 * 60 + 260],
        ];

        foreach ($samples as $sample) {
            PatrolLog::create([
                'satpam_id' => $satpam->id,
                'patrol_point_id' => $patrolPoint->id,
                'scan_time' => now()->subMinutes($sample['minutes_ago']),
                'latitude' => $patrolPoint->latitude,
                'longitude' => $patrolPoint->longitude,
                'distance_from_point' => $sample['scan_status'] === 'anomali' ? 85.5 : 5.2,
                'scan_status' => $sample['scan_status'],
                'note' => $sample['note'],
            ]);
        }
    }
}
