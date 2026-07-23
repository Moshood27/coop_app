<?php

namespace App\Jobs\Middleware;

use Closure;
use Resend\Exceptions\ErrorException;
use Symfony\Component\Mailer\Exception\TransportException;
use Illuminate\Support\Facades\Log;

class HandleResendQuota
{
    /**
     * Process the queued job.
     *
     * @param  mixed  $job
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($job, Closure $next)
    {
        try {
            return $next($job);
        } catch (\Throwable $e) {
            $rootException = $e;

            // If it's a TransportException from Symfony Mailer, get the previous exception
            if ($e instanceof TransportException && $e->getPrevious()) {
                $rootException = $e->getPrevious();
            }

            if ($rootException instanceof ErrorException) {
                $errorType = $rootException->getErrorType();
                $message = $rootException->getMessage();

                if ($errorType === 'daily_quota_exceeded' || $errorType === 'monthly_quota_exceeded' || str_contains($message, 'daily email sending quota') || str_contains($message, 'monthly email sending quota')) {
                    Log::warning('Resend quota reached. Delaying job: ' . (method_exists($job, 'displayName') ? $job->displayName() : get_class($job)));

                    if (method_exists($job, 'release')) {
                        // Release the job back to the queue with a long delay (1 hour)
                        return $job->release(3600);
                    }
                }

                if ($errorType === 'rate_limit_exceeded' || str_contains($message, 'rate limit exceeded')) {
                    Log::info('Resend rate limit exceeded. Delaying job.');

                    if (method_exists($job, 'release')) {
                        // Rate limit is usually temporary, retry in 5 minutes
                        return $job->release(300);
                    }
                }
            }

            throw $e;
        }
    }
}
