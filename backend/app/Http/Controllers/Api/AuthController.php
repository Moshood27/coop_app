<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Models\Setting;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Pennant\Feature;

class AuthController extends Controller
{
    // List of branches for the login dropdown
    public function status()
    {
        $data = [
            'status' => 'ok',
            'mobile_min_version' => Setting::get('mobile_min_version', config('cooperative.mobile_min_version')),
            'mobile_current_version' => Setting::get('mobile_current_version', config('cooperative.mobile_current_version')),
            'maintenance_mode' => (bool) Setting::get('maintenance_mode', config('cooperative.maintenance_mode')),
            'maintenance_message' => Setting::get('maintenance_message', config('cooperative.maintenance_message')),
            'maintenance_until' => Setting::get('maintenance_until', config('cooperative.maintenance_until')),
            'system_announcement' => Setting::get('system_announcement', config('cooperative.system_announcement')),
            'play_store_url' => Setting::get('play_store_url', config('cooperative.play_store_url')),
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
            'app_name' => config('app.name'),
            'transaction_pin_enabled' => (bool) Setting::get('transaction_pin_enabled', true),
            'app_pin_login_enabled' => (bool) Setting::get('app_pin_login_enabled', false),
            'set_transaction_pin_enabled' => (bool) Setting::get('set_transaction_pin_enabled', true),
            'attendance_pin_enabled' => (bool) Setting::get('attendance_pin_enabled', true),
            'attendance_qr_enabled' => (bool) Setting::get('attendance_qr_enabled', true),
            'attendance_apology_enabled' => (bool) Setting::get('attendance_apology_enabled', true),
            'attendance_ble_beacon_enabled' => (bool) Setting::get('attendance_ble_beacon_enabled', true),
            'attendance_fingerprint_enabled' => (bool) Setting::get('attendance_fingerprint_enabled', true),
            'opening_balance_verification_enabled' => (bool) Setting::get('opening_balance_verification_enabled', true),
            'payment_gateways' => [
                'paystack' => (bool) Setting::get('gateway_paystack_enabled', true),
                'flutterwave' => (bool) Setting::get('gateway_flutterwave_enabled', true),
                'monnify' => (bool) Setting::get('gateway_monnify_enabled', true),
                'opay' => (bool) Setting::get('gateway_opay_enabled', true),
                'primary' => Setting::get('primary_payment_gateway', 'paystack'),
            ],
        ];

        if (auth('sanctum')->check()) {
            $data['features'] = [
                'withdrawals_enabled' => Feature::for('global')->active('withdrawals-enabled'),
                'withdrawals-enabled' => Feature::for('global')->active('withdrawals-enabled'),
                'apply_for_loan' => Feature::active('apply-for-loan'),
                'apply-for-loan' => Feature::active('apply-for-loan'),
                'gold_market' => Feature::active('gold-savings-beta'),
                'gold-savings-beta' => Feature::active('gold-savings-beta'),
                'payment_failover' => Feature::for('global')->active('payment-provider-failover'),
                'payment-provider-failover' => Feature::for('global')->active('payment-provider-failover'),
                'shura_voting' => Feature::for('global')->active('shura-voting-active'),
                'shura-voting-active' => Feature::for('global')->active('shura-voting-active'),
                'prayer_quiet_mode' => Feature::for('global')->active('prayer-time-quiet-mode'),
                'prayer-time-quiet-mode' => Feature::for('global')->active('prayer-time-quiet-mode'),
                'show_flw_balance' => Feature::active('show-flw-balance'),
                'show-flw-balance' => Feature::active('show-flw-balance'),
                // New features
                'takaful-enabled' => Feature::for('global')->active('takaful-enabled'),
                'gold-savings-enabled' => Feature::for('global')->active('gold-savings-enabled'),
                'group-savings-enabled' => Feature::for('global')->active('group-savings-enabled'),
                'receive-qr-enabled' => Feature::for('global')->active('receive-qr-enabled'),
                'merchant-pay-enabled' => Feature::for('global')->active('merchant-pay-enabled'),
                'zakat-enabled' => Feature::for('global')->active('zakat-enabled'),
                'junior-coop-enabled' => Feature::for('global')->active('junior-coop-enabled'),
                'projects-enabled' => Feature::for('global')->active('projects-enabled'),
                'project-payment-enabled' => Feature::for('global')->active('project-payment-enabled'),
                'chat-help-enabled' => Feature::for('global')->active('chat-help-enabled'),
                'store-enabled' => Feature::for('global')->active('store-enabled'),
                'hajj-umrah-enabled' => Feature::for('global')->active('hajj-umrah-enabled'),
                'sadaq-enabled' => Feature::for('global')->active('sadaq-enabled'),
                'wassiyah-enabled' => Feature::for('global')->active('wassiyah-enabled'),
                'vendor-enabled' => Feature::for('global')->active('vendor-enabled'),
                'agm-voting-enabled' => Feature::for('global')->active('agm-voting-enabled'),
                'airtime-data-enabled' => Feature::for('global')->active('airtime-data-enabled'),
            ];
        }

        return response()->json($data);
    }

    public function branches()
    {
        return response()->json(Branch::orderBy('name')->get());
    }

    // Branch-based login with membership number
    public function login(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'membership_number' => 'required',
            'password' => 'required',
            'remember' => 'nullable|boolean',
        ]);

        $user = User::where('branch_id', $validated['branch_id'])
            ->where(function ($query) use ($validated) {
                $query->where('membership_number', $validated['membership_number'])
                    ->orWhere('phone', $validated['membership_number']);
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'membership_number' => ['The credentials do not match our records for this branch.'],
            ]);
        }

        if ($user->approval_status !== 'approved') {
            $status = ucfirst($user->approval_status ?: 'pending');
            throw ValidationException::withMessages([
                'membership_number' => ["Your account is {$status}. Please contact the administrator for approval."],
            ]);
        }

        $tokenName = $request->boolean('remember') ? 'remember_token' : 'mobile_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        $response = response()->json([
            'token' => $token,
            'user' => $user,
        ]);

        // Attach HttpOnly cookie for security (mitigate XSS)
        // Lifetime: 30 days if remember, otherwise 2 hours (120 mins)
        $minutes = $request->boolean('remember') ? 43200 : 120;

        return $response->cookie(
            'auth_token',
            $token,
            $minutes,
            '/',
            null,
            true, // secure
            true, // httpOnly
            false, // raw
            'Lax' // sameSite
        );
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }
            // Fire logout event to log activity and clear last_activity_at via listener
            event(new \Illuminate\Auth\Events\Logout('sanctum', $user));
        }

        $response = response()->json(['message' => 'Logged out successfully.']);

        // Clear the auth_token cookie
        return $response->cookie(cookie()->forget('auth_token'));
    }

    /**
     * Member forgot password: send a 6-digit code via email or SMS.
     * Supports both registered Users and Applicants (MemberApplications).
     * Accepts one of: email | phone | (branch_id + membership_number)
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms,push'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['nullable', 'integer'],
            'membership_number' => ['nullable', 'string'],
        ]);

        // Try resolve user silently (do not reveal existence)
        $user = null;
        $isApplicant = false;

        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                $user = \App\Models\MemberApplication::where('email', $data['email'])->whereNull('finalized_at')->first();
                if ($user) $isApplicant = true;
            }
        } elseif (!empty($data['phone'])) {
            $user = User::where('phone', $data['phone'])->first();
            if (!$user) {
                $user = \App\Models\MemberApplication::where('phone', $data['phone'])->whereNull('finalized_at')->first();
                if ($user) $isApplicant = true;
            }
        } elseif (!empty($data['branch_id']) && !empty($data['membership_number'])) {
            $user = User::where('branch_id', $data['branch_id'])
                ->where(function ($query) use ($data) {
                    $query->where('membership_number', $data['membership_number'])
                        ->orWhere('phone', $data['membership_number']);
                })
                ->first();
        }

        // Always respond with generic message to avoid enumeration
        $generic = ['message' => 'If the account exists, a reset code has been sent.'];

        if (!$user) {
            return response()->json($generic);
        }

        // Determine destination
        $hasPush = !empty($user->fcm_token) || !empty($user->device_token);
        $sendEmail = $data['channel'] === 'email' && $user->email;
        $sendSms = $data['channel'] === 'sms' && $user->phone;
        $sendPushExplicit = $data['channel'] === 'push' && $hasPush;

        if (!$sendEmail && !$sendSms && !$sendPushExplicit) {
            // If they chose push but don't have it, or email/sms but don't have it
            return response()->json($generic);
        }

        // Throttle per target: 60s between sends
        $throttleId = ($isApplicant ? 'app_' : 'user_') . $user->id;
        $tkey = 'pwd_reset:throttle:' . $throttleId;
        if (Cache::has($tkey)) {
            return response()->json(['message' => 'Please wait before requesting another code.', 'retry_after' => 60], 429)
                ->header('Retry-After', 60);
        }

        $code = (string) random_int(100000, 999999);

        $cacheKey = 'pwd_reset:' . $throttleId;
        $payload = [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'channel' => $data['channel'],
            'is_applicant' => $isApplicant,
        ];
        Cache::put($cacheKey, $payload, now()->addMinutes(10));
        Cache::put($tkey, 1, now()->addSeconds(60));

        // Determine channel(s)
        // Always send push if available, plus the requested channel
        $notificationChannel = $data['channel'];
        if ($hasPush && $data['channel'] !== 'push') {
            $notificationChannel .= ',push';
        }

        try {
            $user->notify(new OtpNotification(
                title: 'Password Reset Code',
                message: "Your password reset code is {$code}. It expires in 10 minutes.",
                channel: $notificationChannel,
                context: ['type' => 'password_reset']
            ));

            $sentTo = [];
            if ($sendEmail) $sentTo['email'] = $this->maskEmail($user->email);
            if ($sendSms) $sentTo['phone'] = $this->maskPhone($user->phone);
            if ($hasPush) $sentTo['push'] = 'Device notification sent';

            return response()->json([
                'message' => 'If the account exists, a reset code has been sent.',
                'sent_to' => $sentTo,
                'expires_in' => 600,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Password reset notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json($generic);
    }

    /**
     * Member reset password using 6-digit code sent via email or SMS.
     * Supports both registered Users and Applicants (MemberApplications).
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'regex:/^\\d{6}$/'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:6'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['nullable', 'integer'],
            'membership_number' => ['nullable', 'string'],
        ]);

        // Resolve user by provided identifier
        $user = null;
        $isApplicant = false;
        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                $user = \App\Models\MemberApplication::where('email', $data['email'])->whereNull('finalized_at')->first();
                if ($user) $isApplicant = true;
            }
        } elseif (!empty($data['phone'])) {
            $user = User::where('phone', $data['phone'])->first();
            if (!$user) {
                $user = \App\Models\MemberApplication::where('phone', $data['phone'])->whereNull('finalized_at')->first();
                if ($user) $isApplicant = true;
            }
        } elseif (!empty($data['branch_id']) && !empty($data['membership_number'])) {
            $user = User::where('branch_id', $data['branch_id'])
                ->where(function ($query) use ($data) {
                    $query->where('membership_number', $data['membership_number'])
                        ->orWhere('phone', $data['membership_number']);
                })
                ->first();
        }
        if (!$user) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $throttleId = ($isApplicant ? 'app_' : 'user_') . $user->id;
        $cacheKey = 'pwd_reset:' . $throttleId;
        $payload = Cache::get($cacheKey);
        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }
        if (!isset($payload['expires_at']) || time() > (int) $payload['expires_at']) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Code has expired. Please request a new one.'], 422);
        }
        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= 5) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Too many invalid attempts. Please request a new code.'], 429);
        }
        if (!Hash::check($data['code'], (string) $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $payload, now()->addSeconds(max(60, (int)($payload['expires_at'] - time()))));
            return response()->json(['message' => 'Invalid code.'], 403);
        }

        // Update password and clear token
        if ($isApplicant) {
            $user->password_hash = \Illuminate\Support\Facades\Crypt::encryptString($data['password']);
        } else {
            $user->password = $data['password']; // hashed by cast
        }
        $user->save();
        Cache::forget($cacheKey);

        $response = ['message' => 'Password has been reset successfully.'];
        if ($isApplicant) {
            $response['token'] = $user->token;
        } else {
            $response['membership_number'] = $user->membership_number;
        }

        // Send security alert
        try {
            $user->notify(new \App\Notifications\GeneralNotification(
                title: 'Password Changed',
                message: 'Your account password has been successfully reset. If you did not perform this action, please contact support immediately.',
                data: ['type' => 'security_alert']
            ));
        } catch (\Throwable $e) {
            Log::warning('Password reset confirmation notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json($response);
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($digits);
        if ($len <= 4) return '****';
        return str_repeat('*', max(0, $len - 4)).substr($digits, -4);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return '***';
        $name = $parts[0];
        $domain = $parts[1];
        $maskedName = strlen($name) <= 2 ? str_repeat('*', strlen($name)) : substr($name, 0, 1).str_repeat('*', strlen($name)-2).substr($name, -1);
        return $maskedName.'@'.$domain;
    }
}
