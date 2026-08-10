<?php

namespace App\Models;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Contribution extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $casts = [
        'user_id' => 'integer',
        'scheme_id' => 'integer',
        'amount' => 'decimal:2',
        'units' => 'decimal:6',
        'paid_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'scheme_id',
        'project_id',
        'savings_group_id',
        'amount',
        'units',
        'reference',
        'status',
        'category',
        'qard_hasan_id',
        'ledger_journal_id',
        'paid_at',
        'notes',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (MonthClosing::isDateClosed($model->paid_at ?? $model->created_at ?? now())) {
                throw new \Exception("Cannot create contribution for a closed month.");
            }
            if (empty($model->reference)) {
                $model->reference = self::generateReference();
            }
            if ($model->status === 'success' && empty($model->paid_at)) {
                $model->paid_at = now();
            }

            // Auto-infer category based on scheme if not explicitly provided
            if (empty($model->category) && $model->scheme_id) {
                $scheme = $model->scheme ?: Scheme::find($model->scheme_id);
                $schemeName = $scheme?->name;

                if ($schemeName === 'Loan Repayment') {
                    $model->category = 'loan_repayment';
                } elseif ($schemeName === 'Fine') {
                    $model->category = 'fine';
                }
            }
        });

        static::updating(function (self $model) {
            if (MonthClosing::isDateClosed($model->getOriginal('paid_at') ?? $model->getOriginal('created_at'))) {
                throw new \Exception("Cannot update contribution in a closed month.");
            }
            if (MonthClosing::isDateClosed($model->paid_at ?? $model->created_at)) {
                throw new \Exception("Cannot move contribution to a closed month.");
            }
            if ($model->isDirty('status') && $model->status === 'success' && empty($model->paid_at)) {
                $model->paid_at = now();
            }

            // Auto-infer category if scheme changed or category is missing
            if ($model->isDirty('scheme_id') || (empty($model->category) && $model->scheme_id)) {
                $scheme = $model->scheme ?: Scheme::find($model->scheme_id);
                $schemeName = $scheme?->name;

                if ($schemeName === 'Loan Repayment') {
                    $model->category = 'loan_repayment';
                } elseif ($schemeName === 'Fine') {
                    $model->category = 'fine';
                }
            }
        });

        static::deleting(function (self $model) {
            if (MonthClosing::isDateClosed($model->paid_at ?? $model->created_at)) {
                throw new \Exception("Cannot delete contribution in a closed month.");
            }
        });

        static::created(function (self $model) {
            // Sync user scheme balance if successful
            try {
                if ($model->status === 'success' && $model->scheme && $model->category !== 'fine') {
                    $model->user->syncSchemeBalance($model->scheme->name);
                }
            } catch (\Throwable $e) {}

            // Special handling for Fine category
            if ($model->status === 'success' && $model->category === 'fine') {
                try {
                    $user = $model->user;
                    $user->decrement('outstanding_fines', min($user->outstanding_fines, $model->amount));

                    // Settle attendance records
                    app(\App\Services\AttendanceService::class)->settleOutstandingFines($user, (float) $model->amount);

                    \App\Models\CharityEntry::create([
                        'user_id' => $user->id,
                        'source' => 'Manual Fine Payment',
                        'amount' => $model->amount,
                        'note' => "Manual payment of fine (Reference: {$model->reference})",
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);
                } catch (\Throwable $e) {}
            }

            // Special handling for SITTING scheme (Administrative Charges)
            if ($model->status === 'success' && $model->scheme && strtoupper($model->scheme->name) === 'SITTING') {
                try {
                    $user = $model->user;
                    $user->decrement('admin_charge_balance', min($user->admin_charge_balance, (float) $model->amount));
                    $user->update(['last_admin_charge_at' => now()]);
                } catch (\Throwable $e) {}
            }

            // Special handling for Loan Repayment category
            if ($model->status === 'success' && $model->category === 'loan_repayment') {
                try {
                    $user = $model->user;

                    // If a specific loan is linked, use it, otherwise fallback to the first active loan
                    $q = null;
                    if ($model->qard_hasan_id) {
                        $q = QardHasan::find($model->qard_hasan_id);
                    }

                    if (!$q) {
                        $q = QardHasan::where('user_id', $user->id)
                            ->whereIn('status', ['active', 'defaulted'])
                            ->whereColumn('paid_amount', '<', 'principal_amount')
                            ->first();
                    }

                    if ($q && in_array($q->status, ['active', 'defaulted'])) {
                        if (!QardHasanRepayment::where('reference', $model->reference)->exists()) {
                            $before = (float) $q->paid_amount;
                            $remaining = max(0, (float) $q->principal_amount - $before);
                            $applied = round(min((float) $model->amount, $remaining), 2);

                            if ($applied > 0) {
                                QardHasanRepayment::create([
                                    'qard_hasan_id' => $q->id,
                                    'amount' => $applied,
                                    'payment_method' => $model->payment_method,
                                    'reference' => $model->reference,
                                    'status' => 'success',
                                    'paid_at' => $model->paid_at ?? now(),
                                    'notes' => $model->notes,
                                ]);

                                $q->paid_amount = $before + $applied;
                                if ($q->paid_amount >= $q->principal_amount) {
                                    $q->status = 'completed';
                                }
                                $q->save();
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to process loan repayment from contribution: " . $e->getMessage());
                }
            }

            // If created already successful and linked to a project (e.g., wallet allocation), create investment
            try {
                if ($model->project_id && $model->status === 'success') {
                    // Decrement available units if applicable
                    if ($model->units > 0) {
                        $project = Project::find($model->project_id);
                        if ($project && $project->is_unit_based) {
                            $project->decrement('available_units', $model->units);
                        }
                    }

                    if (! ProjectInvestment::where('contribution_id', $model->id)->exists()) {
                        ProjectInvestment::create([
                            'user_id' => $model->user_id,
                            'project_id' => $model->project_id,
                            'contribution_id' => $model->id,
                            'amount' => $model->amount,
                            'units' => $model->units,
                            'reference' => $model->reference,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // Don’t block creation on investment linkage failures
            }
        });

        static::updated(function (self $model) {
            // When a contribution tied to a project is marked successful, create a ProjectInvestment once
            try {
                if ($model->status === 'success' && ($model->wasChanged('status') || $model->wasChanged('amount'))) {
                    // Sync user scheme balance if not a fine
                    try {
                        if ($model->scheme && $model->category !== 'fine') {
                            $model->user->syncSchemeBalance($model->scheme->name);
                        }
                    } catch (\Throwable $e) {}

                    // Special handling for Fine category when marked as success (e.g. from webhook)
                    if ($model->category === 'fine') {
                        try {
                            $user = $model->user;
                            $user->decrement('outstanding_fines', min($user->outstanding_fines, $model->amount));

                            // Settle attendance records
                            app(\App\Services\AttendanceService::class)->settleOutstandingFines($user, (float) $model->amount);

                            \App\Models\CharityEntry::create([
                                'user_id' => $user->id,
                                'source' => 'Manual Fine Payment',
                                'amount' => $model->amount,
                                'note' => "Manual payment of fine (Reference: {$model->reference})",
                                'status' => 'processed',
                                'processed_at' => now(),
                            ]);
                        } catch (\Throwable $e) {}
                    }

                    // Special handling for SITTING scheme (Administrative Charges)
                    if ($model->scheme && strtoupper($model->scheme->name) === 'SITTING') {
                        try {
                            $user = $model->user;
                            $user->decrement('admin_charge_balance', min($user->admin_charge_balance, (float) $model->amount));
                            $user->update(['last_admin_charge_at' => now()]);
                        } catch (\Throwable $e) {}
                    }

                    // Special handling for Loan Repayment category
                    if ($model->category === 'loan_repayment') {
                        try {
                            $user = $model->user;

                            // If a specific loan is linked, use it, otherwise fallback to the first active loan
                            $q = null;
                            if ($model->qard_hasan_id) {
                                $q = QardHasan::find($model->qard_hasan_id);
                            }

                            if (!$q) {
                                $q = QardHasan::where('user_id', $user->id)
                                    ->whereIn('status', ['active', 'defaulted'])
                                    ->whereColumn('paid_amount', '<', 'principal_amount')
                                    ->first();
                            }

                            if ($q && in_array($q->status, ['active', 'defaulted'])) {
                                if (!QardHasanRepayment::where('reference', $model->reference)->exists()) {
                                    $before = (float) $q->paid_amount;
                                    $remaining = max(0, (float) $q->principal_amount - $before);
                                    $applied = round(min((float) $model->amount, $remaining), 2);

                                    if ($applied > 0) {
                                        QardHasanRepayment::create([
                                            'qard_hasan_id' => $q->id,
                                            'amount' => $applied,
                                            'payment_method' => $model->payment_method,
                                            'reference' => $model->reference,
                                            'status' => 'success',
                                            'paid_at' => $model->paid_at ?? now(),
                                            'notes' => $model->notes,
                                        ]);

                                        $q->paid_amount = $before + $applied;
                                        if ($q->paid_amount >= $q->principal_amount) {
                                            $q->status = 'completed';
                                        }
                                        $q->save();
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("Failed to process loan repayment from contribution: " . $e->getMessage());
                        }
                    }

                    // Special handling for Wallet Top-up category
                    if ($model->category === 'wallet_topup') {
                        try {
                            $user = $model->user;
                            $reference = $model->reference;

                            // Idempotency: check if already credited in WalletTransaction
                            if (!WalletTransaction::where('reference', $reference)->exists()) {
                                // Calculate maintenance charge
                                $fee = (float) Setting::get('wallet_topup_fee_fixed', 0);
                                $percent = (float) Setting::get('wallet_topup_fee_percent', 0);
                                $charge = round($fee + ($model->amount * ($percent / 100)), 2);
                                $netAmount = round(max(0, $model->amount - $charge), 2);

                                if ($netAmount > 0) {
                                    DB::transaction(function () use ($user, $netAmount, $model, $charge, $reference) {
                                        $user->increment('balance', $netAmount);
                                        \App\Models\WalletTransaction::create([
                                            'user_id' => $user->id,
                                            'type' => 'credit',
                                            'amount' => $netAmount,
                                            'reference' => $reference,
                                            'source' => $model->payment_method ?: 'payment_gateway',
                                            'meta' => [
                                                'contribution_id' => $model->id,
                                                'original_amount' => $model->amount,
                                                'maintenance_charge' => $charge,
                                            ],
                                            'withdrawable' => true,
                                        ]);
                                    });
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("Failed to process wallet topup from contribution: " . $e->getMessage());
                        }
                    }

                    // Update Attaqwa Score
                    try {
                        app(\App\Services\AttaqwaScoreService::class)->calculateAndUpdateScore($model->user);
                    } catch (\Throwable $e) {}

                    // Notify admins and user about successful contribution
                    try {
                        $user = $model->user;
                        $schemeName = $model->scheme?->name ?? 'Contribution';

                        // Notify user (triggers real-time update)
                        $user->notifyMember(
                            "Contribution Successful",
                            "Your payment of ₦" . number_format($model->amount, 2) . " for {$schemeName} was successful.",
                            ['type' => 'contribution_success', 'contribution_id' => $model->id]
                        );

                        // Notify relevant admins
                        $user->getAuthorizedAdmins()
                            ->filter(fn($admin) => $admin->id !== auth()->id())
                            ->each(function ($admin) use ($user, $model, $schemeName) {
                                $admin->notifyMember(
                                    "Payment Received: {$schemeName}",
                                    "Member {$user->name} successfully paid ₦" . number_format($model->amount, 2) . " for {$schemeName}.",
                                    ['type' => 'contribution_success', 'contribution_id' => $model->id],
                                    null, // default channels
                                    false // broadcast=false to avoid double real-time notifications for admins
                                );
                            });
                    } catch (\Throwable $e) {}

                    if ($model->project_id) {
                        // Decrement available units if applicable
                        if ($model->units > 0) {
                            $project = Project::find($model->project_id);
                            if ($project && $project->is_unit_based) {
                                $project->decrement('available_units', $model->units);
                            }
                        }

                        // Avoid duplicates if re-updated
                        if (! ProjectInvestment::where('contribution_id', $model->id)->exists()) {
                            ProjectInvestment::create([
                                'user_id' => $model->user_id,
                                'project_id' => $model->project_id,
                                'contribution_id' => $model->id,
                                'amount' => $model->amount,
                                'units' => $model->units,
                                'reference' => $model->reference,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Swallow to prevent blocking payment finalization; logs can be added if needed
            }
        });

        static::deleted(function (self $model) {
            // Sync user scheme balance if it was successful
            try {
                if ($model->status === 'success' && $model->scheme) {
                    $model->user->syncSchemeBalance($model->scheme->name);
                }
            } catch (\Throwable $e) {}
        });
    }

    public static function generateReference(): string
    {
        return 'CNTRB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function scheme()
    {
        return $this->belongsTo(Scheme::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function savingsGroup()
    {
        return $this->belongsTo(SavingsGroup::class);
    }

    public function ledgerJournal()
    {
        return $this->belongsTo(LedgerJournal::class);
    }
}
