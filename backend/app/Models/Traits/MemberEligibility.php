<?php

namespace App\Models\Traits;

trait MemberEligibility
{
    public function isAdmin(): bool
    {
        return $this->is_admin || $this->hasRole('super_admin');
    }

    /**
     * Check if user is eligible for Shura (Voting and Project Proposals).
     */
    public function isEligibleForShura(): bool
    {
        if ($this->is_defaulter) {
            return false;
        }

        if ($this->deceased_at) {
            return false;
        }

        return true;
    }

    /**
     * Policy: A loan is active if it is pending, active, or defaulted AND has a remaining balance.
     */
    public function hasActiveLoan(): bool
    {
        return $this->qardHasans()
            ->whereIn('status', ['active', 'pending', 'defaulted'])
            ->whereColumn('paid_amount', '<', 'principal_amount')
            ->where('principal_amount', '>', 0)
            ->exists();
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->hasAnyRole(['Staff', 'Branch Manager', 'Clerk']);
    }

    public function isBoardMember(): bool
    {
        return $this->hasRole('Board Member') || $this->hasRole('super_admin');
    }

    public function isCommitteeMember(): bool
    {
        return $this->hasAnyRole(['Audit Committee', 'Investment Committee', 'Credit Committee']) || $this->isBoardMember();
    }

    public function scopeStaff($query)
    {
        return $query->where(function ($q) {
            $q->where('is_admin', true)
              ->orWhereHas('roles', fn ($rq) => $rq->whereIn('name', ['super_admin', 'Staff', 'Branch Manager', 'Clerk']));
        });
    }

    public function scopeMember($query)
    {
        return $query->where('is_admin', false)
            ->whereDoesntHave('roles', fn ($rq) => $rq->whereIn('name', ['super_admin', 'Staff', 'Branch Manager', 'Clerk']));
    }
}
