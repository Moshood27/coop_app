<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

class WelcomeWidget extends AccountWidget
{
    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 1;
}
