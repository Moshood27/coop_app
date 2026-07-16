<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Auditing extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security & Logs';
}
