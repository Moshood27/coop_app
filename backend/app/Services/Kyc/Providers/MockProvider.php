<?php

namespace App\Services\Kyc\Providers;

class MockProvider
{
    /**
     * Simulate BVN + face verification.
     * Rules:
     *  - 11-digit BVN is required
     *  - Mock mode always returns success for 11-digit BVN (as per KYC_SYSTEM.md)
     *  - Score is 0.92
     */
    public function verifyBvnWithFace(string $bvn, string $selfiePath, ?string $idImagePath = null): array
    {
        $bvn = preg_replace('/[^0-9]/', '', $bvn ?? '');
        if (strlen($bvn) !== 11) {
            return [
                'success' => false,
                'status' => 'invalid_bvn',
                'score' => 0.0,
                'provider' => 'mock',
                'meta' => [
                    'message' => 'BVN must be 11 digits',
                ],
            ];
        }

        $ok = true;
        return [
            'success' => $ok,
            'status' => 'verified',
            'score' => 0.92,
            'provider' => 'mock',
            'meta' => [
                'note' => 'Mocked response for local/dev',
                'bvn' => $bvn,
                'selfie_exists' => is_file(public_path($selfiePath)),
                'id_image_exists' => $idImagePath ? is_file(public_path($idImagePath)) : false,
            ],
        ];
    }
}
