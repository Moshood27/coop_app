<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures Livewire/AJAX requests are treated as JSON by Laravel.
 * This prevents HTML redirects (e.g., login page or 419 error pages)
 * from being returned to the browser when Livewire expects JSON.
 */
class ForceJsonForLivewire
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detect Livewire requests via header or common Livewire endpoint path
        $isLivewire = $request->headers->has('X-Livewire')
            || str_starts_with(trim($request->path(), '/'), 'livewire');

        // For Livewire (or explicit AJAX), force JSON semantics to avoid HTML responses
        if ($isLivewire || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            // Ensure the request is treated as wanting JSON
            $accept = $request->headers->get('Accept', '');
            if (stripos($accept, 'application/json') === false) {
                $request->headers->set('Accept', 'application/json');
            }
            // Mark as AJAX to influence framework behavior
            if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
                $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            }
        }

        return $next($request);
    }
}
