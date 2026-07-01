<?php

namespace App\WebAuthn;

use Illuminate\Contracts\Cache\Repository as Cache;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

class CacheChallengeRepository implements WebAuthnChallengeRepository
{
    /**
     * Create a new challenge repository instance.
     */
    public function __construct(protected Cache $cache)
    {
        //
    }

    /**
     * Puts a ceremony challenge into the repository.
     */
    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        $key = $this->cacheKey($challenge->data->toHex());

        $this->cache->put($key, $challenge, now()->addMinutes(10));
    }

    /**
     * Pulls a ceremony challenge out from the repository, if it exists.
     */
    public function pull(AttestationValidation|AssertionValidation $ceremony): ?Challenge
    {
        $challengeHex = $ceremony->clientDataJson?->challenge->toHex();

        if (!$challengeHex) {
            return null;
        }

        return $this->cache->pull($this->cacheKey($challengeHex));
    }

    /**
     * Generate the cache key for the challenge.
     */
    protected function cacheKey(string $hex): string
    {
        return "webauthn_challenge_$hex";
    }
}
