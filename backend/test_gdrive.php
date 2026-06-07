<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$config = config('filesystems.disks.google');

echo "Testing Google Drive connection...\n";
echo "Client ID: " . substr($config['clientId'], 0, 10) . "...\n";

$client = new \Google\Client();
$client->setClientId($config['clientId']);
$client->setClientSecret($config['clientSecret']);
$client->addScope(\Google\Service\Drive::DRIVE);
$client->setAccessType('offline');

try {
    $token = $client->fetchAccessTokenWithRefreshToken($config['refreshToken']);
    if (isset($token['error'])) {
        echo "Error: " . $token['error'] . "\n";
        echo "Description: " . ($token['error_description'] ?? 'N/A') . "\n";
        print_r($token);
    } else {
        echo "Successfully fetched access token!\n";
        $service = new \Google\Service\Drive($client);
        $about = $service->about->get(['fields' => 'user']);
        echo "Logged in as: " . $about->getUser()->getDisplayName() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
