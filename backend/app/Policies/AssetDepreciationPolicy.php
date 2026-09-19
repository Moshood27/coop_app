<?php

namespace App\Policies;

use App\Models\AssetDepreciation;
use App\Models\User;

class AssetDepreciationPolicy
{
    public function post(User $user, AssetDepreciation $dep): bool
    {
        return $user->can('fixed_assets.post_depreciation');
    }
}
