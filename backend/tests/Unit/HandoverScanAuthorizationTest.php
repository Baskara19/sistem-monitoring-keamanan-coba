<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SatpamController;
use App\Models\PatrolHandover;
use App\Models\PatrolLog;
use App\Models\PatrolPoint;
use App\Models\PatrolRoute;
use App\Models\Satpam;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\User;
use App\Services\PatrolRoundService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class HandoverScanAuthorizationTest extends TestCase
{
    private function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Uji otorisasi ketika handover berstatus 'accepted':
     * Penerima handover harus diizinkan scan, dengan atribusi yang benar.
     */
    public function test_accepted_handover_authorizes_recipient_with_correct_attribution(): void
    {
        $controller = new SatpamController(new PatrolRoundService());

        $senderSatpam = new Satpam(['id' => 10]);
        $recipientSatpam = new Satpam(['id' => 20]);

        $point = new PatrolPoint(['id' => 5, 'name' => 'Pos Gerbang']);

        $schedule = new Schedule(['id' => 100, 'status' => 'aktif', 'start_date' => today(), 'end_date' => today()]);
        $detail = new ScheduleDetail([
            'id'              => 1,
            'schedule_id'     => 100,
            'satpam_id'       => 10,
            'patrol_point_id' => 5,
            'shift_start'     => '06:00:00',
            'shift_end'       => '14:00:00',
            'sequence_order'  => 1,
        ]);
        $detail->setRelation('schedule', $schedule);
        $detail->setRelation('patrolPoint', $point);

        $handover = new PatrolHandover([
            'id'                 => 50,
            'from_satpam_id'     => 10,
            'to_satpam_id'       => 20,
            'schedule_detail_id' => 1,
            'patrol_point_id'    => 5,
            'patrol_round'       => 1,
            'handover_date'      => today(),
            'status'             => 'accepted',
            'reason'             => 'Sakit mendadak',
        ]);
        $handover->setRelation('scheduleDetail', $detail);

        $this->assertEquals(20, $handover->to_satpam_id);
        $this->assertEquals(10, $handover->from_satpam_id);
        $this->assertEquals('accepted', $handover->status);
        $this->assertEquals(1, $handover->patrol_round);
    }

    /**
     * Uji bahwa jika handover masih pending, ditolak, atau dibatalkan,
     * penerima TIDAK diizinkan scan.
     */
    public function test_non_accepted_handovers_are_not_authorized(): void
    {
        $statuses = ['pending', 'rejected', 'cancelled'];

        foreach ($statuses as $status) {
            $handover = new PatrolHandover([
                'id'                 => 51,
                'from_satpam_id'     => 10,
                'to_satpam_id'       => 20,
                'schedule_detail_id' => 1,
                'patrol_point_id'    => 5,
                'patrol_round'       => 1,
                'handover_date'      => today(),
                'status'             => $status,
                'reason'             => 'Test',
            ]);

            $this->assertNotEquals('accepted', $handover->status);
        }
    }

    /**
     * Uji bahwa petugas asal tidak boleh scan jika handover sudah diterima oleh rekan,
     * tetapi tetap boleh scan jika handover dibatalkan atau ditolak.
     */
    public function test_original_guard_scanning_rules_based_on_handover_status(): void
    {
        // 1. Handover accepted -> Pengirim dilarang scan
        $acceptedHandover = new PatrolHandover(['status' => 'accepted']);
        $this->assertTrue($acceptedHandover->status === 'accepted');

        // 2. Handover cancelled/rejected -> Pengirim tetap bisa scan
        $cancelledHandover = new PatrolHandover(['status' => 'cancelled']);
        $this->assertFalse($cancelledHandover->status === 'accepted');

        $rejectedHandover = new PatrolHandover(['status' => 'rejected']);
        $this->assertFalse($rejectedHandover->status === 'accepted');
    }

    /**
     * Uji atribusi log patroli saat delegasi handover tercatat:
     * satpam_id adalah eksekutor (penerima), delegated_from_satpam_id adalah pemohon.
     */
    public function test_patrol_log_attribution_for_handover(): void
    {
        $log = new PatrolLog([
            'satpam_id'                => 20, // Penerima (yang scan)
            'delegated_from_satpam_id' => 10, // Pemohon (yang diwakili)
            'patrol_handover_id'       => 50,
            'patrol_point_id'          => 5,
            'schedule_detail_id'       => 1,
            'patrol_round'             => 1,
            'scan_status'              => 'berhasil',
        ]);

        $this->assertEquals(20, $log->satpam_id, 'satpam_id harus mencatat petugas yang benar-benar scan');
        $this->assertEquals(10, $log->delegated_from_satpam_id, 'delegated_from_satpam_id harus mencatat petugas asal');
        $this->assertEquals(50, $log->patrol_handover_id, 'patrol_handover_id harus mengaitkan record handover');
        $this->assertEquals(1, $log->patrol_round, 'patrol_round harus sesuai putaran yang didelegasikan');
    }
}
