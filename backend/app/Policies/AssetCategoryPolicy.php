<?php

namespace App\Policies;

use App\Models\AssetCategory;
use App\Models\User;

class AssetCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fixed_assets.manage_categories');
    }

    public function view(User $user, AssetCategory $category): bool
    {
        return $user->can('fixed_assets.manage_categories');
    }

    public function create(User $user): bool
    {
        return $user->can('fixed_assets.manage_categories');
    }

    public function update(User $user, AssetCategory $category): bool
    {
        return $user->can('fixed_assets.manage_categories');
    }

    public function delete(User $user, AssetCategory $category): bool
    {
        return $user->can('fixed_assets.manage_categories');
    }
}
