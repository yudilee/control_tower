<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;

$jobs = Job::uninvoiced()->with('vehicle')->limit(10)->get();

foreach ($jobs as $job) {
    echo "Job: {$job->job_number}, Plate: {$job->plate_number}, Vehicle ID: {$job->vehicle_id}, Vehicle Found: " . ($job->vehicle ? 'Yes' : 'No') . ", In Workshop: " . ($job->is_in_workshop ? 'Yes' : 'No') . "\n";
    if (!$job->vehicle) {
        $v = \App\Models\Vehicle::where('plate_number', $job->plate_number)->first();
        if ($v) {
            echo "  -> Found vehicle by plate: ID {$v->id}, In Workshop: " . ($v->is_in_workshop ? 'Yes' : 'No') . "\n";
        } else {
            echo "  -> No vehicle found by plate either.\n";
        }
    }
}
