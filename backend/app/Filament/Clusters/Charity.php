<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Charity extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Charity & Zakat';

    protected static ?string $navigationGroup = 'Finance & Treasury';
}
