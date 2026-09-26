<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\Branch;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Filament\Resources\UserResource\Pages;

trait HasUserForm
{
    public static function getUserForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('deceased_alert')
                    ->hidden(fn (?User $record = null) => $record === null || $record->deceased_at === null)
                    ->content(function (User $record) {
                        return new \Illuminate\Support\HtmlString('<div class="p-4 bg-danger-500/10 text-danger-700 rounded-lg border border-danger-500/20"><strong>DECEASED:</strong> This member is marked as deceased. Please see the <strong>Wasiyyah (Beneficiaries)</strong> tab below for distribution instructions.</div>');
                    })
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('no_loan_alert')
                    ->hidden(fn (?User $record = null) => $record === null || $record->hasActiveLoan())
                    ->content(function (User $record) {
                        $loanUrl = \App\Filament\Resources\QardHasanResource::getUrl('index', ['tableFilters[user_id][value]' => $record->id]);
                        return new \Illuminate\Support\HtmlString("<div class=\"p-4 bg-warning-500/10 text-warning-700 rounded-lg border border-warning-500/20\"><strong>NOTICE:</strong> This member currently has no active or defaulted loan record. Any contributions marked as \"Loan Repayment\" for this member will not be automatically deducted from a loan. <a href=\"{$loanUrl}\" target=\"_blank\" class=\"text-primary-600 underline font-bold\">Manage/Create Loans</a></div>");
                    })
                    ->columnSpanFull(),

                Forms\Components\Tabs::make('User Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Personal & Contact')
                            ->schema([
                                Forms\Components\Section::make('Basic Personal Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')->label('First Name')->required()->maxLength(255),
                                        Forms\Components\TextInput::make('surname')->maxLength(255),
                                        Forms\Components\TextInput::make('other_names')->maxLength(255),
                                        Forms\Components\Select::make('gender')
                                            ->options([
                                                'male' => 'Male',
                                                'female' => 'Female',
                                            ]),
                                        Forms\Components\TextInput::make('native_place')->label('Native (State or Town of Origin)'),
                                        Forms\Components\DatePicker::make('dob')->label('Date of Birth'),
                                        Forms\Components\Select::make('marital_status')
                                            ->options([
                                                'single' => 'Single',
                                                'married' => 'Married',
                                                'divorced' => 'Divorced',
                                                'widow' => 'Widow',
                                            ]),
                                        Forms\Components\TextInput::make('occupation'),
                                        Forms\Components\FileUpload::make('passport_path')
                                            ->label('Passport / Profile Photo')
                                            ->image()
                                            ->disk('public_root')
                                            ->directory('upload')
                                            ->visibility('public')
                                            ->fetchFileInformation(false)
                                            ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames) {
                                                $raw = (string) $file;
                                                $path = ltrim($raw, '/');
                                                $wasStoragePrefixed = false;
                                                if (str_starts_with($path, 'storage/')) {
                                                    $path = substr($path, strlen('storage/'));
                                                    $wasStoragePrefixed = true;
                                                }

                                                $url = null;
                                                $publicFull = public_path($path);
                                                if (is_file($publicFull)) {
                                                    $url = '/'.ltrim($path, '/');
                                                } else {
                                                    $url = $wasStoragePrefixed
                                                        ? ('/storage/'.ltrim($path, '/'))
                                                        : Storage::disk('public')->url($path);

                                                    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                                                        // Keep full URL
                                                    }
                                                }

                                                return [
                                                    'name' => basename($path),
                                                    'size' => 0,
                                                    'type' => null,
                                                    'url' => $url,
                                                ];
                                            })
                                            ->imageEditor()
                                            ->downloadable()
                                            ->openable(),
                                    ])->columns(3),

                                Forms\Components\Section::make('Contact Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Primary Phone')
                                            ->tel()
                                            ->maxLength(20),
                                        Forms\Components\TextInput::make('secondary_phone')
                                            ->label('Secondary Phone')
                                            ->tel()
                                            ->maxLength(20),
                                        Forms\Components\TextInput::make('address')
                                            ->label('Address')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('residential_address')->rows(2),
                                        Forms\Components\Textarea::make('permanent_address')->rows(2),
                                    ])->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Identity & Membership')
                            ->schema([
                                Forms\Components\Section::make('Identity & KYC')
                                    ->schema([
                                        Forms\Components\TextInput::make('bvn')
                                            ->label('BVN')
                                            ->maxLength(11)
                                            ->password()
                                            ->revealable(fn () => auth()->user()->hasRole('super_admin')),
                                        Forms\Components\DateTimePicker::make('bvn_verified_at')
                                            ->label('BVN Verified At')
                                            ->disabled(),
                                        Forms\Components\Toggle::make('is_admin')
                                            ->label('Administrator')
                                            ->helperText('Grants access to this admin panel')
                                            ->visible(fn () => auth()->user()->can('manage_admins')),
                                        Select::make('roles')
                                            ->relationship('roles', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->searchable(),
                                        Forms\Components\Toggle::make('is_defaulter')
                                            ->label('Defaulter')
                                            ->helperText('Restricts certain features for the member'),
                                        Forms\Components\FileUpload::make('id_card_path')->label('ID Card')->image()->disk('public_root')->directory('upload'),
                                        Forms\Components\FileUpload::make('proof_of_address_path')->label('Proof of Address')->image()->disk('public_root')->directory('upload'),
                                        Forms\Components\TextInput::make('biometric_template')
                                            ->label('Fingerprint Template (USB Scanner)')
                                            ->helperText('Capture raw template string from USB scanner service.')
                                            ->password()
                                            ->revealable()
                                            ->columnSpanFull()
                                            ->suffixAction(
                                                Forms\Components\Actions\Action::make('scan')
                                                    ->icon('heroicon-m-finger-print')
                                                    ->color('primary')
                                                    ->action(function () {})
                                                    ->extraAttributes([
                                                        'x-on:click' => new \Illuminate\Support\HtmlString('
                                                            $el.classList.add(\'animate-pulse\');
                                                            window.biometricScanner.captureTemplate()
                                                                .then(template => {
                                                                    $wire.set(\'data.biometric_template\', template);
                                                                    new FilamentNotification()
                                                                        .title(\'Biometric Captured\')
                                                                        .success()
                                                                        .send();
                                                                })
                                                                .catch(err => {
                                                                    new FilamentNotification()
                                                                        .title(\'Scanner Error\')
                                                                        .body(err.message)
                                                                        .danger()
                                                                        .persistent()
                                                                        .send();
                                                                })
                                                                .finally(() => $el.classList.remove(\'animate-pulse\'));
                                                        '),
                                                        'x-on:contextmenu.prevent' => new \Illuminate\Support\HtmlString('window.biometricScanner.showConfigModal()'),
                                                        'title' => 'Left click to scan. Right click for settings.'
                                                    ])
                                            ),
                                    ])->columns(2),

                                Forms\Components\Section::make('Membership')
                                    ->schema([
                                        Forms\Components\Select::make('branch_id')
                                            ->label('Branch')
                                            ->options(Branch::query()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required(),
                                        Forms\Components\Toggle::make('is_distant')
                                            ->label('Distant Member')
                                            ->helperText('Distant members are charged Meeting Fees instead of Sitting Fees.'),
                                        Forms\Components\TextInput::make('membership_number')
                                            ->password()
                                            ->revealable(fn () => auth()->user()->hasRole('super_admin'))
                                            ->maxLength(255)
                                            ->rule(function (Get $get, ?User $record) {
                                                $branchId = $get('branch_id');
                                                $number = $get('membership_number');
                                                if (blank($branchId) || blank($number)) {
                                                    return null;
                                                }
                                                $rule = Rule::unique('users', 'membership_number')
                                                    ->where(fn ($q) => $q->where('branch_id', $branchId));
                                                if ($record) {
                                                    $rule = $rule->ignore($record->id);
                                                }

                                                return $rule;
                                            }),
                                        Forms\Components\TextInput::make('balance')
                                            ->numeric()
                                            ->prefix('₦')
                                            ->default(0)
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('outstanding_fines')
                                            ->label('Outstanding Fines')
                                            ->numeric()
                                            ->prefix('₦')
                                            ->default(0)
                                            ->readOnly()
                                            ->helperText('Total pending lateness and absence fines'),
                                        Forms\Components\DatePicker::make('created_at')
                                            ->label('Date Joined')
                                            ->displayFormat('Y-m-d')
                                            ->maxDate(now())
                                            ->dehydrateStateUsing(function ($state) {
                                                if (empty($state)) {
                                                    return null;
                                                }
                                                try {
                                                    return Carbon::parse($state)->startOfDay();
                                                } catch (\Throwable $e) {
                                                    return $state;
                                                }
                                            }),
                                        Forms\Components\TextInput::make('password')
                                            ->password()
                                            ->revealable()
                                            ->required(fn ($livewire) => $livewire instanceof Pages\CreateUser)
                                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->maxLength(255),
                                    ])->columns(3),

                                Forms\Components\Section::make('Bank Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('bank_name')->label('Bank Name')->maxLength(120)->disabled(),
                                        Forms\Components\TextInput::make('bank_code')->label('Bank Code')->maxLength(20)->disabled(),
                                        Forms\Components\TextInput::make('account_number')->label('Account Number')->password()->revealable(fn () => auth()->user()->hasRole('super_admin'))->maxLength(20)->disabled(),
                                        Forms\Components\TextInput::make('account_name')->label('Account Name (Verified)')->maxLength(255)->disabled(),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Takaful & Zakat')
                            ->schema([
                                Forms\Components\Section::make('Takaful & Notifications')
                                    ->schema([
                                        Forms\Components\Toggle::make('takaful_exempt')->label('Exempt from Takaful charges'),
                                        Forms\Components\Toggle::make('takaful_notify_contacts')->label('Notify guarantors/next-of-kin on settlement')->default(true),
                                        Forms\Components\Group::make([
                                            Forms\Components\Toggle::make('notify_email')->label('Email Notifications')->default(true),
                                            Forms\Components\Toggle::make('notify_sms')->label('SMS Notifications')->default(true),
                                            Forms\Components\Toggle::make('notify_push')->label('Push Notifications')->default(true),
                                        ])->columns(3)->columnSpanFull(),
                                        Forms\Components\DateTimePicker::make('deceased_at')->label('Deceased At')->native(false)->seconds(false),
                                        Forms\Components\DateTimePicker::make('major_loss_at')->label('Major Loss At')->native(false)->seconds(false),
                                    ])->columns(2),

                                Forms\Components\Section::make('Zakat Information')
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('zakat_nisab_crossed_at')->label('Nisab Crossed At')->native(false),
                                        Forms\Components\DateTimePicker::make('zakat_last_paid_at')->label('Last Zakat Paid At')->native(false),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Business & Kin')
                            ->schema([
                                Forms\Components\Section::make('Business & Professional Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('nature_of_business'),
                                        Forms\Components\Textarea::make('business_address')->rows(2),
                                        Forms\Components\Toggle::make('has_other_cooperatives')->label('Other Cooperative Affiliations'),
                                        Forms\Components\Textarea::make('other_cooperative_details')
                                            ->visible(fn (Get $get) => $get('has_other_cooperatives'))
                                            ->rows(2),
                                    ])->columns(2),

                                Forms\Components\Section::make('Next of Kin')
                                    ->schema([
                                        Forms\Components\TextInput::make('nok_name')->label('Next of Kin Name'),
                                        Forms\Components\TextInput::make('nok_phone')->label('Next of Kin Phone'),
                                        Forms\Components\TextInput::make('nok_relationship')->label('Relationship'),
                                        Forms\Components\Textarea::make('nok_address')->label('Next of Kin Address')->rows(2),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Guarantor & Religious')
                            ->schema([
                                Forms\Components\Section::make('Guarantor Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('guarantor_name'),
                                        Forms\Components\TextInput::make('guarantor_phone'),
                                        Forms\Components\TextInput::make('guarantor_occupation'),
                                        Forms\Components\Textarea::make('guarantor_address')->rows(2),
                                        Forms\Components\FileUpload::make('guarantor_signature_path')->label('Guarantor Signature')->image()->disk('public_root')->directory('upload'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Religious Information & Imam\'s Attestation')
                                    ->schema([
                                        Forms\Components\TextInput::make('religious_society_name'),
                                        Forms\Components\TextInput::make('imam_name')->label('Imam/Amir Name'),
                                        Forms\Components\TextInput::make('imam_phone')->label('Imam/Amir Phone'),
                                        Forms\Components\TextInput::make('duration_of_jamma_membership'),
                                        Forms\Components\Textarea::make('mosque_address')->rows(2),
                                        Forms\Components\Toggle::make('imam_approval_status')->label('Imam\'s Approval Status'),
                                        Forms\Components\DateTimePicker::make('imam_approved_at'),
                                        Forms\Components\FileUpload::make('imam_signature_path')->label('Imam Signature')->image()->disk('public_root')->directory('upload'),
                                    ])->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Female Members & Official')
                            ->schema([
                                Forms\Components\Section::make('Information for Female Members (Wali/Spouse Details)')
                                    ->schema([
                                        Forms\Components\TextInput::make('spouse_father_name')->label('Father/Spouse Name'),
                                        Forms\Components\TextInput::make('spouse_father_phone')->label('Father/Spouse Phone'),
                                        Forms\Components\Textarea::make('spouse_father_address')->label('Residential Address')->rows(2),
                                        Forms\Components\Textarea::make('spouse_father_business_address')->label('Business Address')->rows(2),
                                        Forms\Components\FileUpload::make('spouse_father_consent_signature_path')->label('Consent Signature')->image()->disk('public_root')->directory('upload'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Nursing Mother Grace (Admin Verified)')
                                    ->schema([
                                        Forms\Components\Select::make('nursing_mother_status')
                                            ->options([
                                                'pending' => 'Pending Request',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                            ])
                                            ->label('Request Status'),
                                        Forms\Components\DateTimePicker::make('nursing_mother_grace_until')
                                            ->label('Grace Period Ends At')
                                            ->helperText('Approved members are exempt from attendance fines until this date.'),
                                        Forms\Components\FileUpload::make('nursing_mother_proof_path')
                                            ->label('Medical Proof / Scan')
                                            ->disk('public')
                                            ->directory('nursing_mother_proofs')
                                            ->downloadable()
                                            ->openable()
                                            ->columnSpanFull(),
                                        Forms\Components\Toggle::make('is_nursing_mother')
                                            ->label('Currently Pregnant/Nursing (Legacy Toggle)')
                                            ->helperText('Manual override; ideally use the date above.'),
                                        Forms\Components\DatePicker::make('baby_birth_date')
                                            ->label('Baby Birth Date')
                                            ->helperText('Grace applies for ' . \App\Models\Setting::get('nursing_mother_grace_period_months', 3) . ' months from this date.'),
                                    ])->columns(2),

                                Forms\Components\Section::make('Official Use Only')
                                    ->schema([
                                        Forms\Components\TextInput::make('admission_form_number'),
                                        Forms\Components\DatePicker::make('admission_date'),
                                        Forms\Components\TextInput::make('admission_officer_name'),
                                        Forms\Components\Textarea::make('officer_recommendation')->rows(2),
                                        Forms\Components\Select::make('approval_status')
                                            ->options([
                                                'pending' => 'Pending',
                                                'recommended' => 'Recommended',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                            ])
                                            ->required()
                                            ->default('approved'),
                                        Forms\Components\FileUpload::make('president_signature_path')->label('President Signature')->image()->disk('public_root')->directory('upload'),
                                        Forms\Components\DateTimePicker::make('president_signed_at'),
                                        Forms\Components\FileUpload::make('secretary_general_signature_path')->label('Secretary General Signature')->image()->disk('public_root')->directory('upload'),
                                        Forms\Components\DateTimePicker::make('secretary_general_signed_at'),
                                    ])->columns(3),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
