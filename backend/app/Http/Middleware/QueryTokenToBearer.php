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
        // Check for token in query parameter or auth_token cookie
        $token = $request->query('token');
        if (!$token && $request->hasCookie('auth_token')) {
            $token = $request->cookie('auth_token');
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
