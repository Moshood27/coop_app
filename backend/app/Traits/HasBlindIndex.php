<?php

namespace App\Traits;

trait HasBlindIndex
{
    /**
     * Generate a deterministic blind index hash for a value.
     */
    public static function generateBlindIndex(string $value, string $column): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Use a dedicated secret if available, otherwise fallback to APP_KEY
        $key = config('secrets.blind_index_key') ?: config('app.key');

        // We include the column name in the salt to prevent cross-column hash matching
        return hash_hmac('sha256', $value . $column, $key);
    }

    /**
     * Boot the trait to automatically update blind indexes on saving.
     */
    protected static function bootHasBlindIndex(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('phone')) {
                $model->phone_bindex = static::generateBlindIndex($model->phone, 'phone');
            }
            if ($model->isDirty('bvn')) {
                $model->bvn_bindex = static::generateBlindIndex($model->bvn, 'bvn');
            }
        });
    }

    /**
     * Scope a query to search by blind index.
     */
    public function scopeWhereBlindIndex($query, string $column, string $value)
    {
        return $query->where($column . '_bindex', static::generateBlindIndex($value, $column));
    }

    /**
     * Scope a query to search by blind index (OR).
     */
    public function scopeOrWhereBlindIndex($query, string $column, string $value)
    {
        return $query->orWhere($column . '_bindex', static::generateBlindIndex($value, $column));
    }
}
