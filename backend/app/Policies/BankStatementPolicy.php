<?php

namespace App\Policies;

use App\Models\BankStatement;
use App\Models\User;

class BankStatementPolicy
{
    public function create(User $user): bool
    {
        return $user->can('bank_statements.create');
    }

    public function import(User $user, BankStatement $statement): bool
    {
        return $user->can('bank_statements.import');
    }

    public function automatch(User $user, BankStatement $statement): bool
    {
        return $user->can('bank_statements.automatch');
    }
}
