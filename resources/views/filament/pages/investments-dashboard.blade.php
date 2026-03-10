<x-filament-panels::page>
    <div class="flex justify-end mb-4 gap-4 items-center">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Zobrazovať v mene:</span>
        <select wire:model.live="currency" class="bg-white border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-white">
            @foreach($this->currencies as $code => $label)
                <option value="{{ $code }}">{{ $code }}</option>
            @endforeach
        </select>
    </div>

    @livewire(\App\Filament\Widgets\InvestmentKpiOverview::class, ['currency' => $currency])

    <div class="space-y-12 mt-8">
        {{-- 1. Benchmark Chart - Full Width --}}
        <div class="h-[750px] bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-4">
            @livewire(\App\Filament\Widgets\PortfolioBenchmarkChart::class)
        </div>

        {{-- 2. Diversification Section - 3 Columns Side-by-Side --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="h-[350px] bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                @livewire(\App\Filament\Widgets\InvestmentDiversificationChart::class, ['currency' => $currency, 'grouping' => 'asset_type'])
            </div>
            <div class="h-[350px] bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                @livewire(\App\Filament\Widgets\InvestmentDiversificationChart::class, ['currency' => $currency, 'grouping' => 'sector'])
            </div>
            <div class="h-[350px] bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                @livewire(\App\Filament\Widgets\InvestmentDiversificationChart::class, ['currency' => $currency, 'grouping' => 'country'])
            </div>
        </div>
    </div>
</x-filament-panels::page>
