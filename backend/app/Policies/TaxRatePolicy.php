<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax.manage_rates');
    }

    public function view(User $user, TaxRate $rate): bool
    {
        return $user->can('tax.manage_rates');
    }

    public function create(User $user): bool
    {
        return $user->can('tax.manage_rates');
    }

    public function update(User $user, TaxRate $rate): bool
    {
        return $user->can('tax.manage_rates');
    }

    public function delete(User $user, TaxRate $rate): bool
    {
        return $user->can('tax.manage_rates');
    }
}
