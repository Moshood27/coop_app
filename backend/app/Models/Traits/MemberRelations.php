<?php

namespace App\Models\Traits;

use App\Models\AttendanceRecord;
use App\Models\Beneficiary;
use App\Models\Branch;
use App\Models\ChatRoom;
use App\Models\Contribution;
use App\Models\GoalBooking;
use App\Models\JuniorAccount;
use App\Models\LoanPenalty;
use App\Models\ProjectInvestment;
use App\Models\ProjectProfitPayout;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\SavingsGoal;
use App\Models\SavingsGroup;
use App\Models\SavingsGroupMember;
use App\Models\ShariaDispute;
use App\Models\StoreOrder;
use App\Models\SupportMessage;
use App\Models\TakafulContribution;
use App\Models\TakafulPoolEntry;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UtilityTransaction;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

trait MemberRelations
{
    public function badges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function guarantor()
    {
        return $this->belongsTo(User::class, 'guarantor_id');
    }

    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    public function qardHasans()
    {
        return $this->hasMany(QardHasan::class);
    }

    public function qardHasanRepayments()
    {
        return $this->hasManyThrough(QardHasanRepayment::class, QardHasan::class);
    }

    public function storeOrders()
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function shariaDisputes()
    {
        return $this->hasMany(ShariaDispute::class);
    }

    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'owner_user_id');
    }

    public function utilityTransactions()
    {
        return $this->hasMany(UtilityTransaction::class);
    }

    public function savingsGoals()
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function goalBookings()
    {
        return $this->hasMany(GoalBooking::class);
    }

    public function takafulContributions()
    {
        return $this->hasMany(TakafulContribution::class);
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function projectInvestments()
    {
        return $this->hasMany(ProjectInvestment::class);
    }

    public function projectProfitPayouts()
    {
        return $this->hasMany(ProjectProfitPayout::class);
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function juniorAccounts()
    {
        return $this->hasMany(JuniorAccount::class);
    }

    public function takafulPoolEntries()
    {
        return $this->hasMany(TakafulPoolEntry::class);
    }

    public function savingsGroupMembers()
    {
        return $this->hasMany(SavingsGroupMember::class);
    }

    public function savingsGroups()
    {
        return $this->hasManyThrough(SavingsGroup::class, SavingsGroupMember::class, 'user_id', 'id', 'id', 'savings_group_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function meetingAttendanceCount(): int
    {
        return $this->attendanceRecords()
            ->where('status', 'present')
            ->whereHas('meeting', function ($query) {
                $query->where('status', 'audited');
            })
            ->count();
    }

    public function chatRooms()
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_members', 'user_id', 'chat_room_id');
    }

    public function supportMessages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function loanPenalties()
    {
        return $this->hasMany(LoanPenalty::class);
    }

    public function createdSavingsGroups()
    {
        return $this->hasMany(SavingsGroup::class, 'creator_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
