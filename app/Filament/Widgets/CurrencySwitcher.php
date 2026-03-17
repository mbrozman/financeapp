<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Currency;

class CurrencySwitcher extends Widget
{
    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.currency-switcher';

    public function getCurrencies()
    {
        return Currency::orderBy('code')->get();
    }

    public function getCurrentCurrency()
    {
        // Skúšame parametre 'currency' (detail) aj 'table_currency' (zoznam) pre spätú kompatibilitu alebo ich zjednotíme
        return request()->query('currency') ?: request()->query('table_currency');
    }
}
