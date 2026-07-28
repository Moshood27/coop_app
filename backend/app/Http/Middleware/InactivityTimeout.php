<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InactivityTimeout
{
    /**
     * Max allowed inactivity in seconds.
     */
    protected int $timeout = 120; // 2 minutes

    public function __construct()
    {
        $this->timeout = (int) env('INACTIVITY_TIMEOUT_SECONDS', 120);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce for Sanctum authenticated requests with a personal access token
        if ($user && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();

            if ($token) {
                // 1. Session Binding Verification (IP and User Agent)
                if (config('cooperative.security.session_binding.enabled', true)) {
                    $ipMismatch = config('cooperative.security.session_binding.verify_ip', true)
                        && $token->ip_address
                        && $request->ip() !== $token->ip_address;

                    $uaMismatch = config('cooperative.security.session_binding.verify_ua', true)
                        && $token->user_agent
                        && $request->userAgent() !== $token->user_agent;

                    if ($ipMismatch || $uaMismatch) {
                        $reason = $ipMismatch ? 'IP mismatch' : 'User Agent mismatch';

                        \App\Services\SecurityLogger::logSuspiciousAction(
                            "Session hijacking attempt? Binding failure: {$reason}",
                            [
                                'reason' => $reason,
                                'token_id' => $token->id,
                                'token_name' => $token->name,
                                'expected_ip' => $token->ip_address,
                                'actual_ip' => $request->ip(),
                                'expected_ua' => substr($token->user_agent, 0, 150),
                                'actual_ua' => substr($request->userAgent(), 0, 150),
                            ],
                            $user
                        );

                        $token->delete();
                        event(new \Illuminate\Auth\Events\Logout('sanctum', $user));

                        return response()->json([
                            'message' => 'Session binding invalid. Please login again.',
                            'binding_error' => true,
                        ], 401);
                    }
                }

                $lastUsed = $token->last_used_at ?? $token->created_at; // fallback to creation time

                // Enforce inactivity timeout only if it's NOT a 'remember_token'
                if ($token->name !== 'remember_token') {
                    if ($lastUsed && now()->diffInSeconds($lastUsed) > $this->timeout) {
                        // Revoke token and reject request
                        $token->delete();

                        // Fire logout event to log activity and clear last_activity_at via listener
                        event(new \Illuminate\Auth\Events\Logout('sanctum', $user));

                        return response()->json([
                            'message' => 'Session expired due to inactivity.',
                        ], 401);
                    }
                }

                // Proceed with request, then stamp last_used_at after successful handling
                /** @var Response $response */
                $response = $next($request);

                // Update last_used_at to now for future checks
                try {
                    $token->forceFill(['last_used_at' => now()])->save();
                } catch (\Throwable $e) {
                    // Silently ignore if DB is read-only or any unexpected error occurs
                }

                return $response;
            }
        }

        return $next($request);
    }
}
