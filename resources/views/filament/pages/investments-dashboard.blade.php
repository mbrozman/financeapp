<x-filament-panels::page>
    @livewire(\App\Filament\Widgets\InvestmentKpiOverview::class)

    <div class="space-y-6 -mt-4">
        {{-- 1. TABUĽKA VÝKONNOSTI --}}
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            @livewire(\App\Filament\Widgets\PortfolioPerformanceTable::class)
        </div>

        {{-- 2. GRAF DIVERZIFIKÁCIE --}}
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="mb-6 text-center">
                <h2 class="mb-6 text-lg font-bold text-gray-800 dark:text-white tracking-tight">Diverzifikácia podľa tried aktív</h2>
            </div>
            @livewire(\App\Filament\Widgets\AssetTypeDiversificationChart::class)
        </div>
    </div>
</x-filament-panels::page>
