<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\User;

class BankReconciliationPolicy
{
    public function start(User $user): bool
    {
        return $user->can('bank_reconciliation.start');
    }

    public function finalize(User $user, BankReconciliation $rec): bool
    {
        return $user->can('bank_reconciliation.finalize');
    }
}
