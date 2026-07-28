<?php

namespace App\Support;

use Illuminate\Support\Str;

class SecurityUtils
{
    /**
     * Check if a URL is safe for redirection (belongs to the app or allowed domains).
     */
    public static function isSafeCallbackUrl(?string $url): bool
    {
        if (empty($url)) {
            return true;
        }

        $appUrl = config('app.url');
        $frontendUrl = config('app.frontend_url', $appUrl);

        // Parse both URLs
        $parsedUrl = parse_url($url);
        $parsedApp = parse_url($appUrl);
        $parsedFrontend = parse_url($frontendUrl);

        $host = $parsedUrl['host'] ?? null;

        if (!$host) {
            // Relative URL is safe
            return true;
        }

        $allowedHosts = [
            $parsedApp['host'] ?? '',
            $parsedFrontend['host'] ?? '',
            'localhost',
            '127.0.0.1',
        ];

        // Also allow subdomains if necessary, but for now exact match is safer
        return in_array($host, array_filter($allowedHosts));
    }

    /**
     * Ensure a callback URL is safe, returning a default if not.
     */
    public static function safeCallbackUrl(?string $url, ?string $default = null): ?string
    {
        if (empty($url)) {
            return $default;
        }

        if (self::isSafeCallbackUrl($url)) {
            return $url;
        }

        return $default ?? config('app.url');
    }

    /**
     * Filter and validate email addresses.
     * Useful for Resend API which is strict about format.
     *
     * @param string|array|null $emails
     * @return array|string|null
     */
    public static function filterEmail($emails)
    {
        if (empty($emails)) {
            return is_array($emails) ? [] : null;
        }

        if (is_string($emails)) {
            return filter_var($emails, FILTER_VALIDATE_EMAIL) ? $emails : null;
        }

        if (is_array($emails)) {
            return array_values(array_filter($emails, function ($email) {
                return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            }));
        }

        return $emails;
    }
}
