<?php

namespace App\Models\Traits;

use Filament\Panel;

trait MemberAdministration
{
    /**
     * Get admins authorized to receive notifications regarding this user.
     * Includes branch admins and super admins.
     */
    public function getAuthorizedAdmins()
    {
        $query = static::query()->where('is_admin', true);

        return $query->where(function ($q) {
            if ($this->branch_id) {
                // Return admins of the same branch (including branch-bound super admins)
                // AND global super admins (those with no branch_id)
                $q->where('branch_id', $this->branch_id)
                  ->orWhere(function ($sq) {
                      $sq->whereNull('branch_id')
                        ->whereHas('roles', fn ($r) => $r->where('name', 'super_admin'));
                  });
            } else {
                // If member has no branch, only global super admins (no branch_id) are authorized
                $q->whereNull('branch_id')
                  ->whereHas('roles', fn ($sq) => $sq->where('name', 'super_admin'));
            }
        })->get();
    }

    /**
     * Generate a unique 6-digit membership number for a branch.
     */
    public static function generateMembershipNumber(int $branchId): string
    {
        // Try up to 20 attempts to avoid rare collisions
        for ($i = 0; $i < 20; $i++) {
            $num = (string) random_int(100000, 999999);
            $exists = self::where('branch_id', $branchId)->where('membership_number', $num)->exists();
            if (!$exists) return $num;
        }
        // Fallback to timestamp-based unique suffix
        return substr((string) (time() . random_int(10, 99)), -6);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->approval_status !== 'approved') {
            return false;
        }

        return $this->is_admin === true || $this->hasAnyRole(['super_admin', 'Branch Manager', 'Clerk']);
    }
}
