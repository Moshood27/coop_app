<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fixed_assets.manage_assets');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('fixed_assets.manage_assets');
    }

    public function create(User $user): bool
    {
        return $user->can('fixed_assets.manage_assets');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('fixed_assets.manage_assets');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('fixed_assets.manage_assets');
    }

    public function postDepreciation(User $user, Asset $asset): bool
    {
        return $user->can('fixed_assets.post_depreciation');
    }
}
