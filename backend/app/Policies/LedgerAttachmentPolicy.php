<?php

namespace App\Policies;

use App\Models\LedgerAttachment;
use App\Models\User;

class LedgerAttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        // Allow users who can view journals or manage attachments
        return $user->can('journals.view') || $user->can('journals.attach');
    }

    public function view(User $user, LedgerAttachment $attachment): bool
    {
        return $user->can('journals.view') || $user->can('journals.attach');
    }

    public function create(User $user): bool
    {
        return $user->can('journals.attach');
    }

    public function delete(User $user, LedgerAttachment $attachment): bool
    {
        return $user->can('journals.attach');
    }
}
