<?php

namespace App\Models\Traits;

use App\Models\UserVirtualAccount;
use App\Models\ProviderAccount;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasVirtualAccounts
{
    public function virtualAccount(): HasOne
    {
        return $this->hasOne(UserVirtualAccount::class);
    }

    public function providerAccounts(): HasMany
    {
        return $this->hasMany(ProviderAccount::class);
    }

    public function getPaystackCustomerCodeAttribute(): ?string
    {
        return $this->virtualAccount?->paystack_customer_code ?? $this->attributes['paystack_customer_code'] ?? null;
    }

    public function setPaystackCustomerCodeAttribute($value): void
    {
        $this->updateVirtualAccountField('paystack_customer_code', $value);
        $this->attributes['paystack_customer_code'] = $value;
    }

    public function getPaystackAuthorizationCodeAttribute(): ?string
    {
        return $this->virtualAccount?->paystack_authorization_code ?? $this->attributes['paystack_authorization_code'] ?? null;
    }

    public function setPaystackAuthorizationCodeAttribute($value): void
    {
        $this->updateVirtualAccountField('paystack_authorization_code', $value);
        $this->attributes['paystack_authorization_code'] = $value;
    }

    public function getDvaAccountNumberAttribute(): ?string
    {
        return $this->virtualAccount?->dva_account_number ?? $this->attributes['dva_account_number'] ?? null;
    }

    public function setDvaAccountNumberAttribute($value): void
    {
        $this->updateVirtualAccountField('dva_account_number', $value);
        $this->attributes['dva_account_number'] = $value;
    }

    public function getDvaBankNameAttribute(): ?string
    {
        return $this->virtualAccount?->dva_bank_name ?? $this->attributes['dva_bank_name'] ?? null;
    }

    public function setDvaBankNameAttribute($value): void
    {
        $this->updateVirtualAccountField('dva_bank_name', $value);
        $this->attributes['dva_bank_name'] = $value;
    }

    public function getDvaAccountNameAttribute(): ?string
    {
        return $this->virtualAccount?->dva_account_name ?? $this->attributes['dva_account_name'] ?? null;
    }

    public function setDvaAccountNameAttribute($value): void
    {
        $this->updateVirtualAccountField('dva_account_name', $value);
        $this->attributes['dva_account_name'] = $value;
    }

    public function getDvaVerificationMetaAttribute(): ?array
    {
        $value = $this->virtualAccount?->dva_verification_meta ?? $this->attributes['dva_verification_meta'] ?? null;
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function setDvaVerificationMetaAttribute($value): void
    {
        $this->updateVirtualAccountField('dva_verification_meta', $value);
        $this->attributes['dva_verification_meta'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getFlwDvaDataAttribute(): ?array
    {
        $value = $this->virtualAccount?->flw_dva_data ?? $this->attributes['flw_dva_data'] ?? null;
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function setFlwDvaDataAttribute($value): void
    {
        $this->updateVirtualAccountField('flw_dva_data', $value);
        $this->attributes['flw_dva_data'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getMonnifyCustomerReferenceAttribute(): ?string
    {
        return $this->virtualAccount?->monnify_customer_reference ?? $this->attributes['monnify_customer_reference'] ?? null;
    }

    public function setMonnifyCustomerReferenceAttribute($value): void
    {
        $this->updateVirtualAccountField('monnify_customer_reference', $value);
        $this->attributes['monnify_customer_reference'] = $value;
    }

    public function getMonnifyDvaDataAttribute(): ?array
    {
        $value = $this->virtualAccount?->monnify_dva_data ?? $this->attributes['monnify_dva_data'] ?? null;
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function setMonnifyDvaDataAttribute($value): void
    {
        $this->updateVirtualAccountField('monnify_dva_data', $value);
        $this->attributes['monnify_dva_data'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Helper to update the virtual account relationship.
     * This ensures data is synchronized to the new table while keeping the legacy columns.
     */
    protected function updateVirtualAccountField(string $field, $value): void
    {
        $account = $this->virtualAccount;
        if (!$account) {
            $account = new UserVirtualAccount(['user_id' => $this->id]);
            $this->setRelation('virtualAccount', $account);
        }
        $account->$field = $value;

        // If the model exists, save it immediately to keep it in sync
        if ($this->exists) {
            $account->save();
        }
    }

    public function getFlwDvaAccountNumberAttribute(): ?string
    {
        return $this->flw_dva_data['account_number'] ?? null;
    }

    public function getFlwDvaAccountNameAttribute(): ?string
    {
        return $this->flw_dva_data['account_name'] ?? null;
    }

    public function getFlwDvaBankNameAttribute(): ?string
    {
        return $this->flw_dva_data['bank_name'] ?? null;
    }

    public function getFlwDvaBankCodeAttribute(): ?string
    {
        return $this->flw_dva_data['bank_code'] ?? null;
    }

    public function getFlwDvaOrderRefAttribute(): ?string
    {
        return $this->flw_dva_data['order_ref'] ?? null;
    }

    public function getFlwDvaFlwRefAttribute(): ?string
    {
        return $this->flw_dva_data['flw_ref'] ?? null;
    }

    public function getMonnifyDvaAccountNumberAttribute(): ?string
    {
        return $this->virtualAccount?->monnify_dva_data['accountNumber'] ?? null;
    }

    public function getMonnifyDvaAccountNameAttribute(): ?string
    {
        return $this->virtualAccount?->monnify_dva_data['accountName'] ?? null;
    }

    public function getMonnifyDvaBankNameAttribute(): ?string
    {
        return $this->virtualAccount?->monnify_dva_data['bankName'] ?? null;
    }

    public function getMonnifyDvaBankCodeAttribute(): ?string
    {
        return $this->virtualAccount?->monnify_dva_data['bankCode'] ?? null;
    }

    public function getOpayUserReferenceAttribute(): ?string
    {
        return $this->virtualAccount?->opay_user_reference ?? null;
    }

    public function setOpayUserReferenceAttribute($value): void
    {
        $this->updateVirtualAccountField('opay_user_reference', $value);
    }

    public function getOpayDvaDataAttribute(): ?array
    {
        return $this->virtualAccount?->opay_dva_data ?? null;
    }

    public function setOpayDvaDataAttribute($value): void
    {
        $this->updateVirtualAccountField('opay_dva_data', $value);
    }

    public function getOpayDvaAccountNumberAttribute(): ?string
    {
        return $this->virtualAccount?->opay_dva_data['accountNumber'] ?? null;
    }

    public function getOpayDvaAccountNameAttribute(): ?string
    {
        return $this->virtualAccount?->opay_dva_data['accountName'] ?? null;
    }

    public function getOpayDvaBankNameAttribute(): ?string
    {
        return $this->virtualAccount?->opay_dva_data['bankName'] ?? null;
    }
}
