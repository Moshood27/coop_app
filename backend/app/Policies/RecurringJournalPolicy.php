<?php

namespace App\Policies;

use App\Models\RecurringJournal;
use App\Models\User;

class RecurringJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recurring_journals.view');
    }

    public function view(User $user, RecurringJournal $journal): bool
    {
        return $user->can('recurring_journals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('recurring_journals.manage');
    }

    public function update(User $user, RecurringJournal $journal): bool
    {
        return $user->can('recurring_journals.manage');
    }

    public function delete(User $user, RecurringJournal $journal): bool
    {
        return $user->can('recurring_journals.manage');
    }

    public function runNow(User $user, RecurringJournal $journal): bool
    {
        return $user->can('recurring_journals.run');
    }
}
