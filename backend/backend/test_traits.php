<?php
require __DIR__ . '/vendor/autoload.php';

try {
    if (trait_exists('App\Filament\Resources\UserResource\Traits\HasUserForm')) {
        echo "Trait HasUserForm found!\n";
    } else {
        echo "Trait HasUserForm NOT found!\n";
    }

    if (class_exists('App\Filament\Resources\UserResource')) {
        echo "Class UserResource found!\n";
    } else {
        echo "Class UserResource NOT found!\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
