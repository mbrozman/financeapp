<x-filament-panels::page>
    <div class="space-y-2 -mt-4">
        {{-- 1. KPI Karty: Likvidita --}}
        <div>
            @livewire(\App\Filament\Widgets\NetWorthOverview::class)
        </div>

        {{-- 2. Grafy v mriežke pre úsporu miesta --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Graf: Mesačný cashflow --}}
            <div class="bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                @livewire(\App\Filament\Widgets\MonthlyCashflowChart::class)
            </div>

            {{-- Graf: Rozdelenie výdavkov --}}
            <div class="bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                @livewire(\App\Filament\Widgets\ExpenseDrilldownChart::class)
            </div>
        </div>

        {{-- 3. Graf: Mesačný rast (Realita vs. Plán) --}}
        <div class="bg-white dark:bg-gray-800 p-2 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            @livewire(\App\Filament\Widgets\NetWorthRealityVsPlanChart::class)
        </div>
    </div>
</x-filament-panels::page>
