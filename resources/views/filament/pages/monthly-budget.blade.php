<x-filament-panels::page>
    @php $data = $this->getBudgetData(); @endphp
    
    {{-- HLAVIČKA --}}
    <div class="flex items-center justify-between bg-white dark:bg-gray-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
        <x-filament::button wire:click="previousMonth" color="gray" icon="heroicon-m-chevron-left" outlined>Predošlý</x-filament::button>
        <div class="text-center">
            <h2 class="text-xl font-black uppercase tracking-tight">{{ \Carbon\Carbon::parse($selectedMonth . '-01')->translatedFormat('F Y') }}</h2>
            <p class="text-xs text-gray-500 font-bold">Mesačný príjem: {{ number_format($data['income'], 2, ',', ' ') }} €</p>
        </div>
        <x-filament::button wire:click="nextMonth" color="gray" icon="heroicon-m-chevron-right" icon-position="after" outlined>Nasledujúci</x-filament::button>
    </div>

    {{-- VÝPIS PILIEROV --}}
    @foreach($data['pillars'] as $group)
        <div class="mt-8" x-data="{ isOpen: false }">
            
            {{-- VÝPOČET FARBY PRE MASTER BAR --}}
            @php
                $pPercent = (float) $group['pillar_percent'];
                $pColor = '#22c55e'; // Zelená
                if ($pPercent >= 100) $pColor = '#f59e0b'; // Oranžová
                if ($pPercent >= 101) $pColor = '#dc2626'; // Červená
            @endphp

            <div x-on:click="isOpen = !isOpen" class="cursor-pointer group mb-4 bg-gray-50 dark:bg-gray-800/50 p-6 rounded-3xl border border-dashed border-gray-300 dark:border-gray-700 hover:border-primary-500 transition-all">
                <div class="flex justify-between items-end mb-4">
                    <div class="flex items-center gap-3">
                        <div class="transition-transform duration-300" :class="isOpen ? 'rotate-180' : ''">
                            <x-heroicon-m-chevron-down class="w-6 h-6 text-gray-400" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ $group['pillar_name'] }}</h3>
                            <p class="text-sm text-gray-500">Kliknutím <span x-text="isOpen ? 'skryjete' : 'zobrazíte'"></span> detaily</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-black" style="color: {{ $pColor }};">
                            {{ number_format($group['pillar_actual'], 2, ',', ' ') }} €
                        </span>
                        <span class="text-gray-400 font-bold block text-sm">z {{ number_format($group['pillar_limit'], 2, ',', ' ') }} €</span>
                    </div>
                </div>

                {{-- MASTER BAR S INLINE FARBOU --}}
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                    <div class="h-full transition-all duration-1000" 
                         style="width: {{ min($pPercent, 100) }}%; background-color: {{ $pColor }};">
                    </div>
                </div>
            </div>

            {{-- KATEGÓRIE --}}
            <div x-show="isOpen" x-collapse class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 px-2">
                @foreach($group['budgets'] as $item)
                    @php
                        $iPercent = (float) $item['percent'];
                        $iColor = '#22c55e'; // Zelená
                        if ($iPercent >= 100) $iColor = '#f59e0b'; // Oranžová
                        if ($iPercent >= 101) $iColor = '#dc2626'; // Červená
                    @endphp

                    <div class="bg-white dark:bg-gray-900 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 border-l-4" style="border-left-color: {{ $iColor }};">
                        <div class="flex justify-between mb-2">
                            <span class="font-bold text-sm text-gray-600 dark:text-gray-400">{{ $item['category'] }}</span>
                            <span class="font-bold text-sm" style="color: {{ $iPercent > 100 ? '#dc2626' : 'inherit' }};">
                                {{ number_format($item['actual'], 2, ',', ' ') }} €
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 mb-1">
                            <div class="h-1.5 rounded-full" 
                                 style="width: {{ min($iPercent, 100) }}%; background-color: {{ $iColor }};"></div>
                        </div>
                        <div class="flex justify-between text-[9px] text-gray-400 font-bold uppercase">
                            <span>{{ round($iPercent, 0) }}%</span>
                            <span>Limit {{ number_format($item['limit'], 0) }} €</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</x-filament-panels::page>