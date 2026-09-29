<?php

namespace Tests\Unit;

use App\Models\PatrolPoint;
use App\Models\PatrolRoute;
use App\Models\Satpam;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\User;
use App\Services\PatrolRoundService;
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
}

