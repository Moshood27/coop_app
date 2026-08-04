<?php
require __DIR__.'/backend/vendor/autoload.php';
$app = require_once __DIR__.'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Sanctum encrypt_cookies: " . config('sanctum.middleware.encrypt_cookies') . "\n";
echo "Sanctum validate_csrf_token: " . config('sanctum.middleware.validate_csrf_token') . "\n";
