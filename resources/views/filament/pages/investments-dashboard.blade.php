<x-filament-panels::page>
    @livewire(\App\Filament\Widgets\InvestmentKpiOverview::class)

    <div class="space-y-6 mt-6">
        {{-- 1. Benchmark Chart - Full Width --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            @livewire(\App\Filament\Widgets\PortfolioBenchmarkChart::class)
        </div>

        {{-- 2. Performance Comparison - Full Width --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            @livewire(\App\Filament\Widgets\PortfolioPerformanceChart::class)
        </div>

        {{-- 3. Diversification Section - 3 Columns Side-by-Side --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                @livewire(\App\Filament\Widgets\InvestmentDiversificationChart::class, ['grouping' => 'asset_type'])
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                @livewire(\App\Filament\Widgets\InvestmentDiversificationChart::class, ['grouping' => 'sector'])
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                @livewire(\App\Filament\Widgets\InvestmentDiversificationChart::class, ['grouping' => 'country'])
            </div>
        </div>
    </div>
</x-filament-panels::page>
