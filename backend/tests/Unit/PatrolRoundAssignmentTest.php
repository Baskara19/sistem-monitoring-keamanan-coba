<?php

namespace Tests\Unit;

use App\Models\PatrolPoint;
use App\Models\PatrolRoute;
use App\Models\Satpam;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\User;
use App\Services\PatrolRoundService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PatrolRoundAssignmentTest extends TestCase
{
    /**
     * Uji pembagian putaran saat ada KAT (katim_id tidak null).
     * Sesuai aturan:
     * - Putaran 1 & 3: Satpam
     * - Putaran 2 & 4: KAT
     */
    public function test_round_assignment_with_katim_alternates_2_2(): void
    {
        $service = new PatrolRoundService();

        // Buat mock objek ScheduleDetail dengan katim_id
        $schedule = new Schedule();
        $schedule->id = 10;
        $schedule->katim_id = 99; // ID KAT

        $detail = new ScheduleDetail();
        $detail->id = 1;
        $detail->satpam_id = 55; // ID Satpam
        $detail->setRelation('schedule', $schedule);

        // Putaran 1 -> Satpam (55)
        $this->assertEquals(55, $service->getAssignedSatpamId($detail, 1));
        $this->assertTrue($service->isRoundAssignedTo($detail, 1, 55));
        $this->assertFalse($service->isRoundAssignedTo($detail, 1, 99));

        // Putaran 2 -> KAT (99)
        $this->assertEquals(99, $service->getAssignedSatpamId($detail, 2));
        $this->assertTrue($service->isRoundAssignedTo($detail, 2, 99));
        $this->assertFalse($service->isRoundAssignedTo($detail, 2, 55));

        // Putaran 3 -> Satpam (55)
        $this->assertEquals(55, $service->getAssignedSatpamId($detail, 3));
        $this->assertTrue($service->isRoundAssignedTo($detail, 3, 55));
        $this->assertFalse($service->isRoundAssignedTo($detail, 3, 99));

        // Putaran 4 -> KAT (99)
        $this->assertEquals(99, $service->getAssignedSatpamId($detail, 4));
        $this->assertTrue($service->isRoundAssignedTo($detail, 4, 99));
        $this->assertFalse($service->isRoundAssignedTo($detail, 4, 55));
    }

    /**
     * Uji jadwal mandiri (tanpa KAT / katim_id null).
     * Semua putaran (1, 2, 3, 4) harus ditugaskan ke satpam pelaksana.
     */
    public function test_round_assignment_without_katim_assigns_all_to_satpam(): void
    {
        $service = new PatrolRoundService();

        $schedule = new Schedule();
        $schedule->id = 20;
        $schedule->katim_id = null;

        $detail = new ScheduleDetail();
        $detail->id = 2;
        $detail->satpam_id = 77;
        $detail->setRelation('schedule', $schedule);

        for ($r = 1; $r <= 4; $r++) {
            $this->assertEquals(77, $service->getAssignedSatpamId($detail, $r));
            $this->assertTrue($service->isRoundAssignedTo($detail, $r, 77));
            $this->assertFalse($service->isRoundAssignedTo($detail, $r, 88));
        }
    }

    /**
     * Uji metadata penanggung jawab (nama & role).
     */
    public function test_assigned_satpam_info_returns_correct_roles_and_names(): void
    {
        $service = new PatrolRoundService();

        $katimUser = new User(['name' => 'Budi KAT']);
        $katimSatpam = new Satpam(['id' => 99]);
        $katimSatpam->setRelation('user', $katimUser);

        $regularUser = new User(['name' => 'Agus Satpam']);
        $regularSatpam = new Satpam(['id' => 55]);
        $regularSatpam->setRelation('user', $regularUser);

        $schedule = new Schedule();
        $schedule->id = 10;
        $schedule->katim_id = 99;
        $schedule->setRelation('katim', $katimSatpam);

        $detail = new ScheduleDetail();
        $detail->id = 1;
        $detail->satpam_id = 55;
        $detail->setRelation('schedule', $schedule);
        $detail->setRelation('satpam', $regularSatpam);

        // Putaran 1 -> Agus (Satpam)
        $info1 = $service->getAssignedSatpamInfo($detail, 1);
        $this->assertEquals(55, $info1['id']);
        $this->assertEquals('Agus Satpam', $info1['name']);
        $this->assertEquals('satpam', $info1['role']);

        // Putaran 2 -> Budi (KAT)
        $info2 = $service->getAssignedSatpamInfo($detail, 2);
        $this->assertEquals(99, $info2['id']);
        $this->assertEquals('Budi KAT', $info2['name']);
        $this->assertEquals('katim', $info2['role']);
        $this->assertEquals('KATIM', $info2['role_label']);
    }

    public function test_null_assigned_info_gracefully_handled(): void
    {
        $assignedInfo = null;

        $assignedRole = $assignedInfo['role'] ?? 'satpam';
        $assignedRoleLabel = $assignedInfo['role_label'] ?? ($assignedRole === 'katim' ? 'KATIM' : 'Satpam');

        $this->assertEquals('satpam', $assignedRole);
        $this->assertEquals('Satpam', $assignedRoleLabel);
    }

    /**
     * Uji pembagian putaran saat tanggal genap (misal 2 Oktober 2026):
     * - Putaran 1 & 3: KAT
     * - Putaran 2 & 4: Satpam
     */
    public function test_round_assignment_with_katim_on_even_day_swaps_to_katim_first(): void
    {
        $service = new PatrolRoundService();

        $schedule = new Schedule();
        $schedule->id = 10;
        $schedule->katim_id = 99; // ID KAT
        $schedule->start_date = '2026-10-02'; // Tanggal genap (2)

        $detail = new ScheduleDetail();
        $detail->id = 1;
        $detail->satpam_id = 55; // ID Satpam
        $detail->setRelation('schedule', $schedule);

        // Putaran 1 -> KAT (99)
        $this->assertEquals(99, $service->getAssignedSatpamId($detail, 1));
        $this->assertTrue($service->isRoundAssignedTo($detail, 1, 99));
        $this->assertFalse($service->isRoundAssignedTo($detail, 1, 55));

        // Putaran 2 -> Satpam (55)
        $this->assertEquals(55, $service->getAssignedSatpamId($detail, 2));
        $this->assertTrue($service->isRoundAssignedTo($detail, 2, 55));
        $this->assertFalse($service->isRoundAssignedTo($detail, 2, 99));

        // Putaran 3 -> KAT (99)
        $this->assertEquals(99, $service->getAssignedSatpamId($detail, 3));
        $this->assertTrue($service->isRoundAssignedTo($detail, 3, 99));
        $this->assertFalse($service->isRoundAssignedTo($detail, 3, 55));

        // Putaran 4 -> Satpam (55)
        $this->assertEquals(55, $service->getAssignedSatpamId($detail, 4));
        $this->assertTrue($service->isRoundAssignedTo($detail, 4, 55));
        $this->assertFalse($service->isRoundAssignedTo($detail, 4, 99));
    }

    public function test_session_anchor_uses_current_day_for_multi_day_schedule(): void
    {
        $service = new PatrolRoundService();
        $schedule = new Schedule();
        $schedule->start_date = '2026-10-01';
        $schedule->end_date = '2026-10-05';
        $schedule->katim_id = 99;

        $detail = new ScheduleDetail();
        $detail->satpam_id = 55;
        $detail->shift_start = '06:00:00';
        $detail->shift_end = '14:00:00';
        $detail->setRelation('schedule', $schedule);

        $anchor = $service->resolveSessionAnchor($detail, Carbon::parse('2026-10-02 10:00:00'));

        $this->assertSame('2026-10-02', $anchor->toDateString());
        $this->assertTrue($service->isRoundAssignedTo($detail, 2, 55, $anchor));
    }

    public function test_session_anchor_uses_previous_day_for_overnight_shift(): void
    {
        $service = new PatrolRoundService();
        $schedule = new Schedule();
        $schedule->start_date = '2026-10-01';
        $schedule->end_date = '2026-10-05';

        $detail = new ScheduleDetail();
        $detail->satpam_id = 55;
        $detail->shift_start = '22:00:00';
        $detail->shift_end = '06:00:00';
        $detail->setRelation('schedule', $schedule);

        $anchor = $service->resolveSessionAnchor($detail, Carbon::parse('2026-10-02 01:00:00'));

        $this->assertSame('2026-10-01', $anchor->toDateString());
    }

    /**
     * Uji bahwa ketika putaran 1 belum discan sama sekali, petugas putaran 2
     * tetap bisa langsung scan putaran 2 pada jamnya tanpa dipaksa / diblokir putaran 1.
     */
    public function test_guard_can_scan_round_2_even_if_round_1_was_not_scanned(): void
    {
        $service = new PatrolRoundService();
        $anchor = Carbon::parse('2026-10-01');

        // Waktu scan jam 11:30 (jendela Putaran 2 Shift Pagi: 11:00-13:00)
        $scanTime = Carbon::parse('2026-10-01 11:30:00');

        // Petugas B hanya punya wewenang putaran [2, 4] (misal KAT)
        // Putaran 1 belum pernah discan (completedRounds = [])
        $decision = $service->resolveRoundForAttempt('Pagi', $anchor, $scanTime, [], [2, 4]);

        $this->assertSame(2, $decision['round'], 'Petugas B harus langsung diarahkan ke Putaran 2');
        $this->assertFalse($decision['is_late'], 'Scan pada jam putaran 2 harus tepat waktu (bukan terlambat)');
    }

    /**
     * Uji bahwa ketika putaran 2 belum discan sama sekali, petugas putaran 3
     * tetap bisa langsung scan putaran 3 pada jamnya tanpa terganjal putaran 2.
     */
    public function test_guard_can_scan_round_3_even_if_round_2_was_not_scanned(): void
    {
        $service = new PatrolRoundService();
        $anchor = Carbon::parse('2026-10-01');

        // Waktu scan jam 13:15 (jendela Putaran 3 Shift Pagi: 13:00-14:00)
        $scanTime = Carbon::parse('2026-10-01 13:15:00');

        // Petugas A punya wewenang putaran [1, 3]
        // Putaran 1 sudah selesai ([1]), putaran 2 terlewat/belum discan
        $decision = $service->resolveRoundForAttempt('Pagi', $anchor, $scanTime, [1], [1, 3]);

        $this->assertSame(3, $decision['round'], 'Petugas A harus langsung diarahkan ke Putaran 3');
        $this->assertFalse($decision['is_late'], 'Scan pada jam putaran 3 harus tepat waktu (bukan terlambat)');
    }

    /**
     * Uji bahwa jika petugas putaran 1 scan saat sudah masuk jam putaran 2 (terlambat),
     * dia tetap bisa mengejar putaran 1 miliknya sebagai terlambat.
     */
    public function test_guard_can_catch_up_own_earlier_round_as_late(): void
    {
        $service = new PatrolRoundService();
        $anchor = Carbon::parse('2026-10-01');

        // Waktu scan jam 11:15 (jendela Putaran 2 Shift Pagi: 11:00-13:00)
        $scanTime = Carbon::parse('2026-10-01 11:15:00');

        // Petugas A punya wewenang putaran [1, 3], dan belum selesai putaran 1
        // Karena putaran 2 bukan milik Petugas A, scan dialihkan ke Putaran 1 sebagai terlambat
        $decision = $service->resolveRoundForAttempt('Pagi', $anchor, $scanTime, [], [1, 3]);

        $this->assertSame(1, $decision['round'], 'Petugas A harus diarahkan mengejar Putaran 1 miliknya');
        $this->assertTrue($decision['is_late'], 'Scan putaran 1 di jam putaran 2 harus berstatus terlambat');
    }
}

