<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = User::first();
if (!$user) {
    echo "No user found\n";
    exit;
}

Auth::login($user);

$request = Illuminate\Http\Request::create('/broadcasting/auth', 'POST', [
    'socket_id' => '1234.1234',
    'channel_name' => 'private-user.' . ($user->id + 1), // Intentional mismatch
]);

$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
