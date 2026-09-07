<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function show($scheduleId, $satpamId)
    {
        $data = DB::table('schedule_details as sd')
            ->join('patrol_points as pp', 'pp.id', '=', 'sd.patrol_point_id')
            ->leftJoin('patrol_logs as pl', function ($join) {
                $join->on('pl.schedule_detail_id', '=', 'sd.id')
                     ->on('pl.satpam_id', '=', 'sd.satpam_id');
            })
            ->where('sd.schedule_id', $scheduleId)
            ->where('sd.satpam_id', $satpamId)
            ->select([
                'sd.id as schedule_detail_id',
                'sd.schedule_id',
                'sd.satpam_id',
                'pp.name as patrol_point',
                'sd.sequence_order',
                'sd.shift_start',
                'sd.shift_end',
                'pl.id as patrol_log_id',
                'pl.scan_time',
                'pl.scan_status',
                'pl.distance_from_point',
                DB::raw("
                    CASE
                        WHEN pl.id IS NOT NULL
                             AND pl.scan_status = 'berhasil'
                        THEN 'SUDAH SCAN'
                        ELSE 'BELUM SCAN'
                    END AS monitoring_status
                ")
            ])
            ->orderBy('sd.sequence_order')
            ->get();

        return response()->json([
            'success' => true,
            'schedule_id' => $scheduleId,
            'satpam_id' => $satpamId,
            'total_checkpoint' => $data->count(),
            'sudah_scan' => $data->where('monitoring_status', 'SUDAH SCAN')->count(),
            'belum_scan' => $data->where('monitoring_status', 'BELUM SCAN')->count(),
            'data' => $data
        ]);
    }
}