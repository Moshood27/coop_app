<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    // Register a new admin user
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => true,
        ]);

        $tokenResult = $user->createToken('admin_token');
        try {
            $tokenResult->accessToken->forceFill([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])->save();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!str_contains($e->getMessage(), 'ip_address')) throw $e;
        }
        $token = $tokenResult->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    // Admin login with email + password
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->is_admin || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check for 2FA requirement
        if ($user->hasConfirmedTwoFactor()) {
            return response()->json([
                'two_factor_required' => true,
                'user_id' => $user->id,
            ]);
        }

        $tokenResult = $user->createToken('admin_token');
        try {
            $tokenResult->accessToken->forceFill([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])->save();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!str_contains($e->getMessage(), 'ip_address')) throw $e;
        }
        $token = $tokenResult->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ])->withCookie(cookie(
            'admin_token',
            $token,
            120, // 2 hours
            '/',
            null,
            $request->isSecure(),
            true, // httpOnly
            false,
            'Strict'
        ));
    }

    /**
     * Verify 2FA code and complete login
     */
    public function verifyTwoFactor(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'code' => ['required', 'string'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if (!$user->is_admin || !$user->hasConfirmedTwoFactor()) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        try {
            // Retrieve the secret from the BreezySession
            $secret = decrypt($user->twoFactorSecret);
            // Get the authenticator engine from Breezy
            $plugin = filament()->getPanel('admin')->getPlugin('filament-breezy');
            $engine = $plugin->getEngine();

            if (!$engine->verifyKey($secret, $data['code'])) {
                return response()->json([
                    'message' => 'The provided two-factor authentication code was invalid.'
                ], 422);
            }
        } catch (\Throwable $e) {
            \Log::error("2FA verification failed for admin {$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'Authentication failed. Please try again.'], 500);
        }

        $tokenResult = $user->createToken('admin_token');
        try {
            $tokenResult->accessToken->forceFill([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])->save();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!str_contains($e->getMessage(), 'ip_address')) throw $e;
        }
        $token = $tokenResult->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ])->withCookie(cookie(
            'admin_token',
            $token,
            120, // 2 hours
            '/',
            null,
            $request->isSecure(),
            true, // httpOnly
            false,
            'Strict'
        ));
    }

    // Request a password reset link for admin by email
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Ensure the email belongs to an admin account
        $user = User::where('email', $request->email)->where('is_admin', true)->first();
        if (! $user) {
            // Do not reveal that the email doesn't exist or is not admin
            return response()->json(['status' => __(Password::RESET_LINK_SENT)]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        $sentTo = ['email' => $request->email];

        if ($status === Password::RESET_LINK_SENT) {
            // Send push notification if token available
            if (!empty($user->fcm_token) || !empty($user->device_token)) {
                $sentTo['push'] = 'Device notification sent';
                try {
                    $user->notify(new \App\Notifications\GeneralNotification(
                        title: 'Password Reset Link Sent',
                        message: 'A password reset link has been sent to your email address. Please check your inbox.',
                        data: ['type' => 'security_alert', 'context' => 'admin_forgot_password']
                    ));
                } catch (\Throwable $e) {
                    \Log::warning('Admin forgot password push failed', ['error' => $e->getMessage()]);
                }
            }
            return response()->json([
                'status' => __($status),
                'sent_to' => $sentTo
            ]);
        }

        return response()->json([
            'message' => __($status),
        ], 422);
    }
}
