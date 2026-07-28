<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = new \App\Models\User();
$methods = get_class_methods($user);
$twoFactorMethods = array_filter($methods, fn($m) => stripos($m, 'twofactor') !== false || stripos($m, '2fa') !== false);

echo "Two Factor Methods found: " . implode(', ', $twoFactorMethods) . "\n";

$traits = class_uses_recursive(\App\Models\User::class);
echo "Traits: " . implode(', ', $traits) . "\n";
