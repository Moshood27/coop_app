<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CookieTokenToBearer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If no Authorization header is present, check for 'token' or 'admin_token' in cookies.
        if (!$request->bearerToken()) {
            $token = $request->cookie('token') ?? $request->cookie('admin_token');

            if ($token) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        // Ensure the request is treated as an AJAX/API request to avoid redirects
        // but only if it doesn't already have an Accept header
        if (!$request->headers->has('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
