<?php

namespace Laravel\Sanctum {
    class TransientToken {
        public function __get($name) {
            trigger_error("Undefined property: Laravel\Sanctum\TransientToken::$" . $name, E_USER_NOTICE);
        }
    }
}

namespace App\Http\Middleware {
    use Closure;
    use Illuminate\Http\Request;
    use Symfony\Component\HttpFoundation\Response;

    // We'll mock the middleware class since we want to test its logic without full Laravel environment
    class InactivityTimeoutMock
    {
        protected int $timeout = 120;

        public function handle(Request $request, Closure $next): Response
        {
            $user = $request->user();

            if ($user && method_exists($user, 'currentAccessToken')) {
                $token = $user->currentAccessToken();

                if ($token) {
                    // This is line 36 in the original file
                    $lastUsed = $token->last_used_at ?? $token->created_at; 

                    if ($token->name !== 'remember_token') {
                        // ...
                    }
                }
            }

            return $next($request);
        }
    }
}

namespace {
    use App\Http\Middleware\InactivityTimeout;
    use Laravel\Sanctum\TransientToken;
    use Illuminate\Http\Request;

    require __DIR__ . '/backend/vendor/autoload.php';
    // We don't need to full boot the app if we just want to test the middleware logic
    // but we need the environment for 'now()' and other Laravel helpers if they are used.
    $app = require_once __DIR__ . '/backend/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    class MockUser {
        public function currentAccessToken() {
            return new TransientToken();
        }
    }

    $middleware = new InactivityTimeout();
    $request = new Request();
    $request->setUserResolver(function() {
        return new MockUser();
    });

    try {
        // Set error handler to convert notices to exceptions to match the issue report
        set_error_handler(function($errno, $errstr) {
            throw new \ErrorException($errstr);
        });

        echo "Testing Inactivity Middleware...\n";
        $middleware->handle($request, function($req) {
            return new \Symfony\Component\HttpFoundation\Response("OK");
        });
        echo "Middleware Success: No error thrown\n";

        echo "\nTesting AuthController@logout...\n";
        $controller = new \App\Http\Controllers\Api\AuthController();
        $controller->logout($request);
        echo "Logout Success: No error thrown\n";

    } catch (\ErrorException $e) {
        echo "Caught error: " . $e->getMessage() . "\n";
    } catch (\Throwable $e) {
        echo "Caught unexpected error: " . get_class($e) . ": " . $e->getMessage() . "\n";
    }
}
