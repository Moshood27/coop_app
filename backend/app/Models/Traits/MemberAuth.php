<?php

namespace App\Models\Traits;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

trait MemberAuth
{
    public function hasTransactionPin(): bool
    {
        return ! empty($this->transaction_pin_hash);
    }

    public function verifyTransactionPin(?string $pin): bool
    {
        if (!Setting::get('transaction_pin_enabled', true)) {
            return true;
        }

        if (! $pin || empty($this->transaction_pin_hash)) {
            return false;
        }

        return Hash::check($pin, $this->transaction_pin_hash);
    }
}
