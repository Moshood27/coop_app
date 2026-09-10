<?php

namespace App\Services\Kyc;

use App\Services\Kyc\Providers\DojahProvider;
use App\Services\Kyc\Providers\MockProvider;
use App\Services\Kyc\Providers\PaystackProvider;

class KycVerifier
{
    public function verifyBvnWithFace(string $bvn, string $selfiePath, ?string $idImagePath = null): array
    {
        $provider = config('kyc.provider', 'mock');
        $driver = match ($provider) {
            'dojah' => new DojahProvider(),
            'paystack' => new PaystackProvider(),
            default => new MockProvider(),
        };

        return $driver->verifyBvnWithFace($bvn, $selfiePath, $idImagePath);
    }
}
