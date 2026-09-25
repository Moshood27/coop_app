<?php

use Illuminate\Support\Facades\Route;
/**
 * API Versioning Wrapper
 *
 * Existing routes are kept at the root for backward compatibility with legacy mobile app versions.
 * New versions of the API should be registered under the 'v1' prefix.
 */

// v1 Versioned Routes
Route::prefix('v1')->group(base_path('routes/api_v1.php'));

// Legacy Root Routes (to be deprecated)
require base_path('routes/api_v1.php');
