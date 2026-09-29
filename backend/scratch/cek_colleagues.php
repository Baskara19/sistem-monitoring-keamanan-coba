<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$s3 = App\Models\Satpam::with(['user', 'scheduleDetails.schedule'])->find(3);
echo "Satpam 3: {$s3->user?->name}\n";
foreach ($s3->scheduleDetails as $d) {
    echo "  - Detail {$d->id}, Shift: {$d->shift_start}-{$d->shift_end}, Dates: {$d->schedule?->start_date} to {$d->schedule?->end_date}, Status: {$d->schedule?->status}\n";
}
