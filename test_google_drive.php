<?php

require __DIR__ . '/backend/vendor/autoload.php';

use Dotenv\Dotenv;
use Google\Client;
use Google\Service\Drive;

// Load .env
if (file_exists(__DIR__ . '/backend/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/backend');
    $dotenv->load();
}

$clientId = $_ENV['GOOGLE_DRIVE_CLIENT_ID'] ?? null;
$clientSecret = $_ENV['GOOGLE_DRIVE_CLIENT_SECRET'] ?? null;
$refreshToken = $_ENV['GOOGLE_DRIVE_REFRESH_TOKEN'] ?? null;

echo "Checking credentials...\n";
echo "Client ID: " . ($clientId ? "SET (" . strlen($clientId) . " chars)" : "NOT SET") . "\n";
echo "Client Secret: " . ($clientSecret ? "SET (" . strlen($clientSecret) . " chars)" : "NOT SET") . "\n";
echo "Refresh Token: " . ($refreshToken ? "SET (" . strlen($refreshToken) . " chars)" : "NOT SET") . "\n";

if (!$clientId || !$clientSecret || !$refreshToken) {
    echo "Missing credentials in .env\n";
    exit(1);
}

$client = new Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->addScope(Drive::DRIVE);

try {
    echo "Attempting to fetch access token with refresh token...\n";
    $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
    
    if (isset($token['error'])) {
        echo "Error fetching access token: " . ($token['error_description'] ?? $token['error']) . "\n";
        print_r($token);
        exit(1);
    }
    
    echo "Access token fetched successfully!\n";
    
    $service = new Drive($client);
    echo "Attempting to list files (limit 1) to verify connectivity...\n";
    $files = $service->files->listFiles(['pageSize' => 1]);
    
    echo "Successfully connected to Google Drive!\n";
    echo "Found " . count($files->getFiles()) . " files.\n";
    
} catch (Exception $e) {
    echo "An exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
