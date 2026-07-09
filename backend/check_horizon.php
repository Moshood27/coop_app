<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

$masterRepo = app(MasterSupervisorRepository::class);
$masters = $masterRepo->all();

echo "Masters: " . count($masters) . "\n";
foreach ($masters as $master) {
    echo "Master: {$master->name}, Status: {$master->status}\n";
}

$supervisorRepo = app(SupervisorRepository::class);
$supervisors = $supervisorRepo->all();

echo "Supervisors: " . count($supervisors) . "\n";
foreach ($supervisors as $supervisor) {
    echo "Supervisor: {$supervisor->name}, Master: {$supervisor->master}, Status: {$supervisor->status}\n";
}
