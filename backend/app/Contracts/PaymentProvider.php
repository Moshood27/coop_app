<?php

namespace App\Contracts;

use App\Models\User;

interface PaymentProvider
{
    /**
     * Ensure customer exists and is synced on the provider's side
     */
    public function syncCustomer(User $user, ?string $phone = null): array;

    /**
     * Create a virtual account for the user
     */
    public function createVirtualAccount(User $user): array;

    /**
     * Get the provider identifier (e.g. 'paystack')
     */
    public function getProviderName(): string;
}
