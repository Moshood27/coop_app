<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\ProviderAccount;
use Illuminate\Support\Manager;
use App\Services\PaystackService;

class PaymentManager extends Manager
{
    public function getDefaultDriver()
    {
        return config('cooperative.payment.default_provider', 'paystack');
    }

    protected function createPaystackDriver()
    {
        return app(PaystackService::class);
    }

    // Future drivers
    protected function createMonnifyDriver()
    {
        // return app(MonnifyService::class);
    }

    protected function createFlutterwaveDriver()
    {
        // return app(FlutterwaveService::class);
    }
}
