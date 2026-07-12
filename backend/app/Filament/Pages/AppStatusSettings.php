<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Laravel\Pennant\Feature;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AppStatusSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'App Status';
    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.app-status-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mobile_min_version' => Setting::get('mobile_min_version', config('cooperative.mobile_min_version')),
            'mobile_current_version' => Setting::get('mobile_current_version', config('cooperative.mobile_current_version')),
            'maintenance_mode' => (bool) Setting::get('maintenance_mode', config('cooperative.maintenance_mode')),
            'maintenance_message' => Setting::get('maintenance_message', config('cooperative.maintenance_message')),
            'maintenance_until' => Setting::get('maintenance_until', config('cooperative.maintenance_until')),
            'system_announcement' => Setting::get('system_announcement', config('cooperative.system_announcement')),
            'play_store_url' => Setting::get('play_store_url', config('cooperative.play_store_url')),
            'loan_credit_score_enabled' => (bool) Setting::get('loan_credit_score_enabled', true),
            'required_loan_meetings' => (int) Setting::get('required_loan_meetings', config('cooperative.attendance.required_loan_meetings', 8)),
            'nursing_mother_grace_period_months' => (int) Setting::get('nursing_mother_grace_period_months', 3),
            'wallet_maintenance_charge_percentage' => Setting::get('wallet_maintenance_charge_percentage', config('cooperative.wallet.maintenance_charge.percentage')),
            'wallet_maintenance_charge_max' => Setting::get('wallet_maintenance_charge_max', config('cooperative.wallet.maintenance_charge.max_amount')),
            'gateway_paystack_enabled' => (bool) Setting::get('gateway_paystack_enabled', true),
            'gateway_flutterwave_enabled' => (bool) Setting::get('gateway_flutterwave_enabled', true),
            'gateway_monnify_enabled' => (bool) Setting::get('gateway_monnify_enabled', true),
            'gateway_opay_enabled' => (bool) Setting::get('gateway_opay_enabled', true),
            'primary_payment_gateway' => Setting::get('primary_payment_gateway', 'paystack'),
            'takaful_enabled' => Feature::for('global')->active('takaful-enabled'),
            'gold_savings_enabled' => Feature::for('global')->active('gold-savings-enabled'),
            'group_savings_enabled' => Feature::for('global')->active('group-savings-enabled'),
            'receive_qr_enabled' => Feature::for('global')->active('receive-qr-enabled'),
            'merchant_pay_enabled' => Feature::for('global')->active('merchant-pay-enabled'),
            'zakat_enabled' => Feature::for('global')->active('zakat-enabled'),
            'junior_coop_enabled' => Feature::for('global')->active('junior-coop-enabled'),
            'projects_enabled' => Feature::for('global')->active('projects-enabled'),
            'chat_help_enabled' => Feature::for('global')->active('chat-help-enabled'),
            'store_enabled' => Feature::for('global')->active('store-enabled'),
            'hajj_umrah_enabled' => Feature::for('global')->active('hajj-umrah-enabled'),
            'sadaq_enabled' => Feature::for('global')->active('sadaq-enabled'),
            'wassiyah_enabled' => Feature::for('global')->active('wassiyah-enabled'),
            'vendor_enabled' => Feature::for('global')->active('vendor-enabled'),
            'agm_voting_enabled' => Feature::for('global')->active('agm-voting-enabled'),
            'airtime_data_enabled' => Feature::for('global')->active('airtime-data-enabled'),
            'withdrawals_enabled' => Feature::for('global')->active('withdrawals-enabled'),
            'wellness_check_enabled' => (bool) Setting::get('wellness_check_enabled', true),
            'wellness_check_inactivity_months' => (int) Setting::get('wellness_check_inactivity_months', config('cooperative.legacy.inactivity_months', 6)),
            'wellness_check_period_days' => (int) Setting::get('wellness_check_period_days', config('cooperative.legacy.check_period_days', 30)),
            'transaction_pin_enabled' => (bool) Setting::get('transaction_pin_enabled', true),
            'app_pin_login_enabled' => (bool) Setting::get('app_pin_login_enabled', false),
            'set_transaction_pin_enabled' => (bool) Setting::get('set_transaction_pin_enabled', true),
            'attendance_pin_enabled' => (bool) Setting::get('attendance_pin_enabled', true),
            'attendance_qr_enabled' => (bool) Setting::get('attendance_qr_enabled', true),
            'attendance_apology_enabled' => (bool) Setting::get('attendance_apology_enabled', true),
            'attendance_ble_beacon_enabled' => (bool) Setting::get('attendance_ble_beacon_enabled', true),
            'attendance_fingerprint_enabled' => (bool) Setting::get('attendance_fingerprint_enabled', true),
            'sitting_fee_amount' => Setting::get('sitting_fee_amount', config('cooperative.admin_charges.amount', 300)),
            'meeting_fee_amount' => Setting::get('meeting_fee_amount', 1000),
            'monthly_fees_enabled' => (bool) Setting::get('monthly_fees_enabled', true),
            'loan_duration_rules' => json_decode(Setting::get('loan_duration_rules', '[]'), true) ?: [
                ['max_amount' => 1000000, 'duration' => 12],
                ['max_amount' => 2000000, 'duration' => 15],
                ['max_amount' => null, 'duration' => 18],
            ],
            'onboarding_swiper_enabled' => (bool) Setting::get('onboarding_swiper_enabled', true),
            'onboarding_swiper_slides' => json_decode(Setting::get('onboarding_swiper_slides', '[]'), true) ?: [
                [
                    'title' => 'Manage Your Savings',
                    'description' => 'Save and track your contributions with ease. Withdraw to wallet when you need it.',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 text-emerald-600"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
                ],
                [
                    'title' => 'Request Loans',
                    'description' => 'Apply for halal-friendly Qard Hasan loans directly in the app.',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 text-emerald-600"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>'
                ],
                [
                    'title' => 'Instant Notifications',
                    'description' => 'Stay updated about approvals, disbursements, and account activity.',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 text-emerald-600"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" /></svg>'
                ],
                [
                    'title' => 'Bills, VTU, and Store',
                    'description' => 'Top-up airtime & data, pay bills, and shop products with your wallet.',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 text-emerald-600"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>'
                ],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Monthly Fees')
                    ->description('Manage monthly sitting and meeting fees.')
                    ->schema([
                        TextInput::make('sitting_fee_amount')
                            ->label('Sitting Fee (Regular)')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->helperText('Monthly fee for regular members.'),
                        TextInput::make('meeting_fee_amount')
                            ->label('Meeting Fee (Distant)')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->helperText('Monthly fee for distant members from branch.'),
                        Toggle::make('monthly_fees_enabled')
                            ->label('Enable Auto-Debits')
                            ->helperText('Automatically debit these fees on the 1st of every month.')
                            ->default(true),
                    ])->columns(3),
                Section::make('Forced Update')
                    ->description('Manage minimum version requirements for native mobile apps.')
                    ->schema([
                        TextInput::make('mobile_min_version')
                            ->label('Minimum Mobile Version')
                            ->required()
                            ->helperText('Users on versions lower than this will be forced to update.'),
                        TextInput::make('mobile_current_version')
                            ->label('Latest Recommended Version')
                            ->required()
                            ->helperText('Users on older versions will see a non-blocking update prompt.'),
                        TextInput::make('play_store_url')
                            ->label('Play Store URL')
                            ->url()
                            ->required(),
                    ]),
                Section::make('Maintenance Mode')
                    ->description('Put the mobile app into maintenance mode.')
                    ->schema([
                        Toggle::make('maintenance_mode')
                            ->label('Enable Maintenance Mode'),
                        Textarea::make('maintenance_message')
                            ->label('Maintenance Message')
                            ->rows(3),
                        TextInput::make('maintenance_until')
                            ->label('Estimated Duration')
                            ->placeholder('e.g., Approximately 1 hour'),
                    ]),
                Section::make('Announcements')
                    ->description('Display a global announcement banner on the dashboard.')
                    ->schema([
                        Textarea::make('system_announcement')
                            ->label('Announcement Text')
                            ->rows(2)
                            ->helperText('Leave empty to hide the announcement.'),
                    ]),
                Section::make('Onboarding Swiper')
                    ->description('Manage the onboarding slides shown to users upon first login.')
                    ->schema([
                        Toggle::make('onboarding_swiper_enabled')
                            ->label('Enable Onboarding Swiper')
                            ->default(true),
                        Repeater::make('onboarding_swiper_slides')
                            ->label('Slides')
                            ->schema([
                                TextInput::make('title')->required(),
                                Textarea::make('description')->required()->rows(2),
                                Textarea::make('icon')
                                    ->label('Icon (SVG)')
                                    ->required()
                                    ->helperText('Paste the SVG code for the icon.'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->collapsible(),
                    ]),
                Section::make('Loan Settings')
                    ->description('Manage loan-related policy settings.')
                    ->schema([
                        Toggle::make('loan_credit_score_enabled')
                            ->label('Enable Credit Score for Loans')
                            ->helperText('If disabled, the Coop credit score will not be used to determine loan eligibility boost or guarantor requirements.')
                            ->default(true),
                        TextInput::make('required_loan_meetings')
                            ->label('Required Meeting Attendance')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->helperText('The minimum number of meetings a member must attend to be eligible for loan approval (e.g., 8). Admins can still approve manually if below this.'),
                        Repeater::make('loan_duration_rules')
                            ->label('Loan Duration Rules')
                            ->schema([
                                TextInput::make('max_amount')
                                    ->label('Maximum Amount (NGN)')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->placeholder('e.g. 1000000')
                                    ->helperText('Leave empty for "Above" (the catch-all rule)'),
                                TextInput::make('duration')
                                    ->label('Duration (Months)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => isset($state['max_amount'])
                                ? "Up to ₦" . number_format($state['max_amount']) . ": " . $state['duration'] . " months"
                                : "Above: " . ($state['duration'] ?? '?') . " months")
                            ->helperText('Define loan duration based on principal amount. Rules are evaluated in order.'),
                    ]),
                Section::make('Grace Period Settings')
                    ->description('Manage grace periods for members.')
                    ->schema([
                        TextInput::make('nursing_mother_grace_period_months')
                            ->label('Nursing Mother Grace Period (Months)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('The number of months a nursing mother is exempt from attendance fines after childbirth or approval.'),
                    ]),
                Section::make('Wellness Check Settings')
                    ->description('Manage wellness check notifications for inactive members.')
                    ->schema([
                        Toggle::make('wellness_check_enabled')
                            ->label('Enable Wellness Check')
                            ->helperText('If enabled, inactive members will receive wellness check notifications.'),
                        TextInput::make('wellness_check_inactivity_months')
                            ->label('Inactivity Threshold (Months)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Number of months of inactivity before sending a wellness check.'),
                        TextInput::make('wellness_check_period_days')
                            ->label('Admin Alert Period (Days)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Number of days to wait after notification before alerting admins if the member remains inactive.'),
                    ])->columns(3),
                Section::make('Security & Verification')
                    ->description('Manage PIN requirements for transactions and attendance.')
                    ->schema([
                        Toggle::make('transaction_pin_enabled')
                            ->label('Enable Transaction PIN')
                            ->helperText('If disabled, members will not be prompted for their transaction PIN when making payments or transfers.')
                            ->default(true),
                        Toggle::make('app_pin_login_enabled')
                            ->label('Enable Login PIN')
                            ->helperText('If enabled, members will be required to enter their transaction PIN to access the app after login.')
                            ->default(false),
                        Toggle::make('set_transaction_pin_enabled')
                            ->label('Enable Set Security PIN Prompt')
                            ->helperText('If enabled, members who have not set their transaction PIN will be prompted to do so on the dashboard.')
                            ->default(true),
                        Toggle::make('attendance_pin_enabled')
                            ->label('Enable Attendance PIN')
                            ->helperText('If disabled, members will not be prompted for a meeting PIN when marking attendance (GPS and Device verification still apply).')
                            ->default(true),
                        Toggle::make('attendance_qr_enabled')
                            ->label('Enable Attendance QR Scanning')
                            ->helperText('If disabled, the option to scan a QR code for attendance will be hidden from members.')
                            ->default(true),
                        Toggle::make('attendance_apology_enabled')
                            ->label('Enable Submit Apology')
                            ->helperText('If disabled, members will not be able to submit apologies for meetings.')
                            ->default(true),
                        Toggle::make('attendance_ble_beacon_enabled')
                            ->label('Enable BLE Beacon')
                            ->helperText('If disabled, members will not be able to use BLE Beacons for attendance.')
                            ->default(true),
                        Toggle::make('attendance_fingerprint_enabled')
                            ->label('Enable Fingerprint')
                            ->helperText('If disabled, members will not be able to use Fingerprint for attendance.')
                            ->default(true),
                    ])->columns(2),
                Section::make('Wallet Settings')
                    ->description('Manage wallet maintenance and transaction charges.')
                    ->schema([
                        TextInput::make('wallet_maintenance_charge_percentage')
                            ->label('Maintenance Charge Percentage (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Percentage of the top-up amount charged as system maintenance fee.'),
                        TextInput::make('wallet_maintenance_charge_max')
                            ->label('Maximum Maintenance Charge (NGN)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('₦')
                            ->helperText('The maintenance charge will be capped at this amount.'),
                    ]),
                Section::make('Payment Gateways')
                    ->description('Enable or disable payment gateways globally and select the primary provider. Disabling a gateway also hides its Dedicated Virtual Account (DVA) from members.')
                    ->schema([
                        Select::make('primary_payment_gateway')
                            ->label('Primary Payment Gateway')
                            ->options([
                                'paystack' => 'Paystack',
                                'flutterwave' => 'Flutterwave',
                                'monnify' => 'Monnify',
                                'opay' => 'Opay',
                            ])
                            ->required(),
                        Toggle::make('gateway_paystack_enabled')
                            ->label('Enable Paystack'),
                        Toggle::make('gateway_flutterwave_enabled')
                            ->label('Enable Flutterwave'),
                        Toggle::make('gateway_monnify_enabled')
                            ->label('Enable Monnify'),
                        Toggle::make('gateway_opay_enabled')
                            ->label('Enable Opay'),
                    ])->columns(2),
                Section::make('Dashboard Features')
                    ->description('Enable or disable specific features on the member dashboard.')
                    ->schema([
                        Toggle::make('takaful_enabled')->label('Takaful'),
                        Toggle::make('gold_savings_enabled')->label('Gold Savings'),
                        Toggle::make('group_savings_enabled')->label('Group Savings'),
                        Toggle::make('receive_qr_enabled')->label('Receive QR'),
                        Toggle::make('merchant_pay_enabled')->label('Merchant Pay'),
                        Toggle::make('zakat_enabled')->label('Zakat'),
                        Toggle::make('junior_coop_enabled')->label('Junior Coop'),
                        Toggle::make('projects_enabled')->label('Projects'),
                        Toggle::make('project_payment_enabled')->label('Project Payments'),
                        Toggle::make('chat_help_enabled')->label('Chat & Help'),
                        Toggle::make('store_enabled')->label('Store'),
                        Toggle::make('hajj_umrah_enabled')->label('Hajj & Umrah'),
                        Toggle::make('sadaq_enabled')->label('Sadaq'),
                        Toggle::make('wassiyah_enabled')->label('Wassiyah'),
                        Toggle::make('vendor_enabled')->label('Vendor'),
                        Toggle::make('agm_voting_enabled')->label('AGM & Voting'),
                        Toggle::make('airtime_data_enabled')->label('Airtime/Data'),
                        Toggle::make('withdrawals_enabled')->label('Withdrawals Enabled')
                            ->helperText('Global kill switch for all withdrawals.'),
                    ])->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $features = [
            'takaful_enabled' => 'takaful-enabled',
            'gold_savings_enabled' => 'gold-savings-enabled',
            'group_savings_enabled' => 'group-savings-enabled',
            'receive_qr_enabled' => 'receive-qr-enabled',
            'merchant_pay_enabled' => 'merchant-pay-enabled',
            'zakat_enabled' => 'zakat-enabled',
            'junior_coop_enabled' => 'junior-coop-enabled',
            'projects_enabled' => 'projects-enabled',
            'project_payment_enabled' => 'project-payment-enabled',
            'chat_help_enabled' => 'chat-help-enabled',
            'store_enabled' => 'store-enabled',
            'hajj_umrah_enabled' => 'hajj-umrah-enabled',
            'sadaq_enabled' => 'sadaq-enabled',
            'wassiyah_enabled' => 'wassiyah-enabled',
            'vendor_enabled' => 'vendor-enabled',
            'agm_voting_enabled' => 'agm-voting-enabled',
            'airtime_data_enabled' => 'airtime-data-enabled',
            'withdrawals_enabled' => 'withdrawals-enabled',
        ];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $features)) {
                if ($value) {
                    Feature::for('global')->activate($features[$key]);
                } else {
                    Feature::for('global')->deactivate($features[$key]);
                }
            } else {
                Setting::set($key, is_array($value) ? json_encode($value) : $value);
            }
        }

        Notification::make()
            ->success()
            ->title('Settings saved successfully.')
            ->send();
    }
}
