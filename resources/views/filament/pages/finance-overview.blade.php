<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 1. KPI Karty: Majetok a likvidita --}}
        @livewire(\App\Filament\Widgets\NetWorthOverview::class)

        {{-- 2. Grafy --}}
        <div class="space-y-6">
            {{-- Graf: Mesačný cashflow (Príjmy vs. Výdavky) - celá šírka --}}
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 h-[420px]">
                @livewire(\App\Filament\Widgets\MonthlyCashflowChart::class)
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 h-[420px]">
            @livewire(\App\Filament\Widgets\ExpenseDrilldownChart::class)
        </div>

        {{-- 3. Graf: Mesačný rast (Realita vs. Plán) - celá šírka --}}
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 h-[900px]">
            @livewire(\App\Filament\Widgets\NetWorthRealityVsPlanChart::class)
        </div>
    </div>
</x-filament-panels::page>
