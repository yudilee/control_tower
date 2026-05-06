<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\Vehicle;

echo "Starting to fix Job-Vehicle links...\n";

$count = 0;
Job::whereNull('vehicle_id')->whereNotNull('plate_number')->chunk(100, function ($jobs) use (&$count) {
    foreach ($jobs as $job) {
        $vehicle = Vehicle::where('plate_number', $job->plate_number)->first();
        if ($vehicle) {
            $job->update(['vehicle_id' => $vehicle->id]);
            $count++;
        }
    }
    echo "Processed " . $jobs->count() . " jobs, fixed {$count} so far...\n";
});

echo "Finished. Total links fixed: {$count}\n";
