<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

class OnlineMembersWidget extends Widget
{
    protected static string $view = 'filament.widgets.online-members-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'recentUsers' => User::where('last_activity_at', '>=', now()->subMinutes(15))
                ->orderBy('last_activity_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'membership_number' => $user->membership_number,
                    'activity' => 'Active recently',
                    'is_fallback' => true,
                ]),
        ];
    }
}
