<?php
use Illuminate\Support\Facades\Route;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = Route::getRoutes();
$found = false;
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'guarantor-reg-requests')) {
        echo "Found route: " . $route->uri() . " [" . implode(',', $route->methods()) . "]\n";
        $found = true;
    }
}

if (!$found) {
    echo "Route guarantor-reg-requests NOT found!\n";
}
