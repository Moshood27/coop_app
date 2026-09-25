<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scheme;
use App\Models\Setting;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;          // Required for v4/v5
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity; // Clean Namespace
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements FilamentUser, WebAuthnAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable, WebAuthnAuthentication, SoftDeletes;
    use Traits\HasVirtualAccounts;
    use Traits\MemberFinancials;
    use Traits\MemberRelations;
    use Traits\MemberEligibility;
    use Traits\MemberNotifications;
    use Traits\MemberAuth;
    use Traits\MemberAdministration;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'device_token',
        'fcm_token',
        'password',
        'branch_id',
        'is_distant',
        'membership_number',
        'balance',
        'outstanding_fines',
        'gold_balance',
        'ordinary_savings',
        'special_savings_balance',
        'shares_capital',
        'building_balance',
        'development_fund_balance',
        'agm_balance',
        'loan_repayment_balance',
        'fine_balance',
        'welfare_balance',
        'lateness_balance',
        'stationery_balance',
        'loan_form_balance',
        'others_balance',
        'id_card_balance',
        'emergency_balance',
        'entrance_balance',
        'h_savings_balance',
        'investment_balance',
        'group_savings_balance',
        'takaful_balance',
        'dawah_fund_balance',
        'sitting_balance',
        'created_at',
        'is_admin',
        'is_defaulter',
        'loan_penalty_until',
        'passport_path',
        'bvn',
        'bvn_verified_at',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'autosave_enabled',
        'autosave_amount',
        'autosave_weekday',
        'autosave_last_run_at',
        'deceased_at',
        'major_loss_at',
        'takaful_exempt',
        'takaful_notify_contacts',
        'notify_email',
        'notify_sms',
        'notify_push',
        'attaqwa_score',
        'last_activity_at',
        'wellness_check_notified_at',
        'zakat_nisab_crossed_at',
        'zakat_last_paid_at',
        'nursing_mother_status',
        'nursing_mother_grace_until',
        'nursing_mother_proof_path',
        'is_nursing_mother',
        'baby_birth_date',
        'admin_charge_balance',
        'admin_charge_auto_deduct',
        'last_admin_charge_at',
        'migrated_at',
        'verified_at',
        'discrepancy_reported_at',
        'biometric_template',
        // Membership Enrolment Form Fields
        'surname',
        'other_names',
        'gender',
        'native_place',
        'dob',
        'marital_status',
        'occupation',
        'secondary_phone',
        'residential_address',
        'permanent_address',
        'nature_of_business',
        'business_address',
        'has_other_cooperatives',
        'other_cooperative_details',
        'nok_name',
        'nok_address',
        'nok_phone',
        'nok_relationship',
        'guarantor_name',
        'guarantor_address',
        'guarantor_phone',
        'guarantor_occupation',
        'guarantor_signature_path',
        'guarantor_id',
        'guarantor_status',
        'guarantor_responded_at',
        'religious_society_name',
        'imam_name',
        'mosque_address',
        'imam_phone',
        'duration_of_jamma_membership',
        'imam_approval_status',
        'imam_approved_at',
        'imam_signature_path',
        'id_card_path',
        'proof_of_address_path',
        'spouse_father_name',
        'spouse_father_address',
        'spouse_father_business_address',
        'spouse_father_phone',
        'spouse_father_consent_signature_path',
        'admission_form_number',
        'admission_date',
        'admission_officer_name',
        'officer_recommendation',
        'approval_status',
        'president_signature_path',
        'president_signed_at',
        'secretary_general_signature_path',
        'secretary_general_signed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'transaction_pin_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_defaulter' => 'boolean',
            'is_distant' => 'boolean',
            'balance' => 'decimal:2',
            'outstanding_fines' => 'decimal:2',
            'ordinary_savings' => 'decimal:2',
            'special_savings_balance' => 'decimal:2',
            'shares_capital' => 'decimal:2',
            'building_balance' => 'decimal:2',
            'development_fund_balance' => 'decimal:2',
            'agm_balance' => 'decimal:2',
            'loan_repayment_balance' => 'decimal:2',
            'fine_balance' => 'decimal:2',
            'welfare_balance' => 'decimal:2',
            'lateness_balance' => 'decimal:2',
            'stationery_balance' => 'decimal:2',
            'loan_form_balance' => 'decimal:2',
            'others_balance' => 'decimal:2',
            'id_card_balance' => 'decimal:2',
            'emergency_balance' => 'decimal:2',
            'entrance_balance' => 'decimal:2',
            'h_savings_balance' => 'decimal:2',
            'investment_balance' => 'decimal:2',
            'group_savings_balance' => 'decimal:2',
            'takaful_balance' => 'decimal:2',
            'dawah_fund_balance' => 'decimal:2',
            'sitting_balance' => 'decimal:2',
            'bvn_verified_at' => 'datetime',
            'pin_set_at' => 'datetime',
            'autosave_enabled' => 'boolean',
            'autosave_amount' => 'decimal:2',
            'autosave_weekday' => 'integer',
            'autosave_last_run_at' => 'datetime',
            'deceased_at' => 'datetime',
            'major_loss_at' => 'datetime',
            'takaful_exempt' => 'boolean',
            'takaful_notify_contacts' => 'boolean',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_push' => 'boolean',
            'gold_balance' => 'decimal:6',
            'last_activity_at' => 'datetime',
            'wellness_check_notified_at' => 'datetime',
            'zakat_nisab_crossed_at' => 'datetime',
            'zakat_last_paid_at' => 'datetime',
            'loan_penalty_until' => 'datetime',
            'nursing_mother_grace_until' => 'datetime',
            'is_nursing_mother' => 'boolean',
            'baby_birth_date' => 'date',
            'admin_charge_balance' => 'decimal:2',
            'admin_charge_auto_deduct' => 'boolean',
            'last_admin_charge_at' => 'datetime',
            'migrated_at' => 'datetime',
            'verified_at' => 'datetime',
            'discrepancy_reported_at' => 'datetime',
            'dob' => 'date',
            'admission_date' => 'date',
            'has_other_cooperatives' => 'boolean',
            'imam_approval_status' => 'boolean',
            'imam_approved_at' => 'datetime',
            'guarantor_responded_at' => 'datetime',
            'president_signed_at' => 'datetime',
            'secretary_general_signed_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::saving(function ($user) {
            // Auto-approve if legacy fields are set manually but status is missing (Nursing Mother Grace)
            if (($user->is_nursing_mother || $user->baby_birth_date || $user->nursing_mother_grace_until) && is_null($user->nursing_mother_status)) {
                $user->nursing_mother_status = 'approved';
            }
        });
    }


    protected $appends = [
        'full_name',
        'permission_names',
        'passport_url',
    ];

    public function getPassportUrlAttribute(): ?string
    {
        if (!$this->passport_path) {
            return null;
        }

        $path = ltrim((string) $this->passport_path, '/');
        if (is_file(public_path($path))) {
            return asset($path);
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->surname} {$this->name} {$this->other_names}");
    }

    public function getPermissionNamesAttribute()
    {
        try {
            return $this->getAllPermissions()->pluck('name');
        } catch (\Throwable $e) {
            \Log::warning("Failed to get permissions for user {$this->id}: " . $e->getMessage());
            return collect([]);
        }
    }



}
