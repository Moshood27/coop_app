<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$paths = [
    'upload/apps/7e7d488d-a793-43fb-8ea2-4651bdd1ff02/passport-1776372029.jpg',
    'upload/01KX6MVP6RY78C5K6WJDKH3SDE.jpg',
];

echo "FILESYSTEM_DISK: " . env('FILESYSTEM_DISK') . "\n";
echo "Local disk root: " . Config::get('filesystems.disks.local.root') . "\n";

foreach ($paths as $path) {
    echo "Path: $path\n";
    echo "  Exists on local: " . (Storage::disk('local')->exists($path) ? 'YES' : 'NO') . "\n";
    echo "  Exists on public: " . (Storage::disk('public')->exists($path) ? 'YES' : 'NO') . "\n";
    
    $fullPathLocal = Config::get('filesystems.disks.local.root') . '/' . $path;
    echo "  Full path (local): $fullPathLocal\n";
    echo "  File exists (filesystem): " . (file_exists($fullPathLocal) ? 'YES' : 'NO') . "\n";
    
    $fullPathPublic = public_path($path);
    echo "  Full path (public): $fullPathPublic\n";
    echo "  File exists (public): " . (file_exists($fullPathPublic) ? 'YES' : 'NO') . "\n";
}
