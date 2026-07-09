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
        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            // A conservative Permissions-Policy to reduce surface area; extend as needed for your app
            'Permissions-Policy' => "accelerometer=(), camera=(self), geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()",
            // Helps isolate browsing context (good default for SPAs and APIs)
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://js.paystack.co https://checkout.flutterwave.com https://embed.tawk.to https://*.tawk.to https://maps.googleapis.com https://maps.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://embed.tawk.to https://*.tawk.to https://maps.googleapis.com https://maps.gstatic.com; font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net https://embed.tawk.to https://*.tawk.to; img-src 'self' data: https: blob:; connect-src 'self' https: wss: blob: https://*.tawk.to wss://*.tawk.to; frame-src 'self' https://js.paystack.co https://checkout.flutterwave.com https://tawk.to https://*.tawk.to;",
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
