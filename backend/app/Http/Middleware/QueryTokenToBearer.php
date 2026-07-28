<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueryTokenToBearer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If the 'token' query parameter exists, use it.
        $token = $request->query('token');

        // 2. Fallback to 'token' or 'admin_token' from HttpOnly cookies if present.
        if (!$token) {
            $token = $request->cookie('token') ?: $request->cookie('admin_token');
        }

        if ($token) {
            if (!$request->bearerToken()) {
                 $request->headers->set('Authorization', 'Bearer ' . $token);
            }

            // Ensure the request is treated as an AJAX/API request to avoid redirects
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        return $next($request);
    }
}
