<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Skip adding these headers for OPTIONS preflight requests to avoid CORS interference.
        if ($request->isMethod('OPTIONS')) {
            return $response;
        }

        // Do not override headers if already set upstream (e.g., reverse proxy)
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://js.paystack.co https://checkout.flutterwave.com https://*.monnify.com https://embed.tawk.to https://*.tawk.to",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://*.tawk.to",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data: https://*.tawk.to",
            "img-src 'self' data: https: blob: https://*.tawk.to",
            "connect-src 'self' https: wss: https://*.paystack.co https://*.paystack.com https://*.flutterwave.com https://*.monnify.com https://*.sentry.io https://*.googleapis.com https://*.tawk.to wss://*.tawk.to",
            "frame-src 'self' https://js.paystack.co https://checkout.flutterwave.com https://*.monnify.com https://*.paystack.com https://*.tawk.to",
            "object-src 'none'",
            "base-uri 'self'",
        ];

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '1; mode=block',
            'Content-Security-Policy' => implode('; ', $csp),
            'Content-Security-Policy-Report-Only' => implode('; ', $csp),
            // A conservative Permissions-Policy to reduce surface area; extend as needed for your app
            'Permissions-Policy' => "accelerometer=(), camera=(self), geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()",
            // Helps isolate browsing context (good default for SPAs and APIs)
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $key => $value) {
            if (!$response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        // Only send HSTS when the original request is HTTPS
        if ($request->isSecure() && !$response->headers->has('Strict-Transport-Security')) {
            // 6 months, include subdomains, preload opt-in
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains; preload');
        }

        return $response;
    }
}
