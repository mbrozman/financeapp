<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return ! auth()->user() || ! auth()->user()->is_superadmin;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
        ];
    }
}
