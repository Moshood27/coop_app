<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use Illuminate\Support\Facades\Route;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Find a user with a token
$user = User::whereNotNull('membership_number')->first();
if (!$user) {
    die("No user found\n");
}

$token = $user->createToken('test-token')->plainTextToken;

echo "Testing /api/test-api\n";
$request = Illuminate\Http\Request::create('/api/test-api', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n";

echo "\nTesting /api/guarantor-reg-requests\n";
$request = Illuminate\Http\Request::create('/api/guarantor-reg-requests', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n";
