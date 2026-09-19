<?php

namespace App\Policies;

use App\Models\LedgerJournal;
use App\Models\User;

class LedgerJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journals.view');
    }

    public function view(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('journals.create');
    }

    public function update(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.create');
    }

    public function delete(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.create');
    }

    public function submit(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.submit');
    }

    public function approve(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.approve');
    }

    public function reject(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.approve');
    }

    public function attach(User $user, LedgerJournal $journal): bool
    {
        return $user->can('journals.attach');
    }
}
