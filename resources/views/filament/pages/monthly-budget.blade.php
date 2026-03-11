<x-filament-panels::page>
    @php $data = $this->getBudgetData(); @endphp

    <div class="flex flex-col gap-y-8"> {{-- Medzera medzi Headerom, Kartami a Piliermi --}}

        {{-- 1. NAVIGÁCIA MESIACOV --}}
        <div class="fi-section p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="flex items-center justify-between">
                <x-filament::button wire:click="previousMonth" color="gray" icon="heroicon-m-chevron-left" outlined>
                    Predošlý
                </x-filament::button>

                <div class="text-center">
                    <h2 class="text-2xl font-black uppercase tracking-tight text-gray-950 dark:text-white">
                        {{ \Carbon\Carbon::parse($selectedMonth . '-01')->translatedFormat('F Y') }}
                    </h2>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1 italic">
                        Príjem: {{ number_format($data['actual_income'] ?? 0, 2, ',', ' ') }} €
                    </p>
                </div>

                <x-filament::button wire:click="nextMonth" color="gray" icon="heroicon-m-chevron-right" icon-position="after" outlined>
                    Nasledujúci
                </x-filament::button>
            </div>
        </div>

        {{-- 2. HLAVNÉ KARTY --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Karta Príjem --}}
            <div class="fi-section p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Mesačný Príjem</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-gray-950 dark:text-white">{{ number_format($data['actual_income'] ?? 0, 2, ',', ' ') }} €</span>
                    <span class="text-xs font-bold {{ ($data['income_diff'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($data['income_diff'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($data['income_diff'] ?? 0, 2) }}
                    </span>
                </div>
                <p class="text-[10px] text-gray-500 mt-4 font-bold">PLÁN: {{ number_format($data['planned_income'] ?? 0, 0) }} €</p>
            </div>

            {{-- Karta Výdavky (VYMAZANÝ TEXT INVEST:) --}}
            <div class="fi-section p-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Celkové odtoky</p>
                <span class="text-3xl font-black text-gray-950 dark:text-white">
                    {{ number_format(($data['total_spent'] ?? 0) + ($data['total_invested'] ?? 0), 2, ',', ' ') }} €
                </span>
                <p class="text-[10px] text-gray-500 mt-4 font-bold uppercase">Suma všetkých mesačných výdavkov</p>
            </div>

            {{-- Karta Cash-flow --}}
            <div class="fi-section p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm border-l-8 {{ ($data['savings'] ?? 0) >= 0 ? 'bg-white border-green-500 dark:bg-gray-900' : 'bg-white border-red-500 dark:bg-gray-900' }}">
                <p class="text-[10px] font-black {{ ($data['savings'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }} uppercase tracking-widest mb-2">Mesačný výsledok</p>
                <span class="text-3xl font-black {{ ($data['savings'] ?? 0) >= 0 ? 'text-gray-950 dark:text-white' : 'text-red-700' }}">
                    {{ number_format($data['savings'] ?? 0, 2, ',', ' ') }} €
                </span>
                <p class="text-[10px] text-gray-400 mt-4 font-bold uppercase">{{ ($data['savings'] ?? 0) >= 0 ? 'Prebytok' : 'Deficit' }}</p>
            </div>
        </div>

        {{-- 3. PILIERE --}}
        <div>
            <div class="flex items-center gap-4 mb-6 px-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Strategické piliere</span>
                <div class="h-px bg-gray-200 dark:bg-gray-800 flex-1"></div>
            </div>

            @foreach($data['pillars'] as $group)
                @php
                    $pPercent = (float) $group['percent'];
                    $pColor = $pPercent >= 101 ? '#dc2626' : ($pPercent >= 100 ? '#f59e0b' : '#22c55e');
                @endphp

                <div x-data="{ isOpen: false }" 
                     class="fi-section bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden"
                     style="margin-bottom: 32px;"> 
                    
                    {{-- Master Bar --}}
                    <div x-on:click="isOpen = !isOpen" class="cursor-pointer p-6 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div class="flex justify-between items-center mb-6">
                            <div class="flex items-center gap-4">
                                <div class="transition-transform duration-300" :class="isOpen ? 'rotate-180' : ''">
                                    <x-heroicon-m-chevron-down class="w-6 h-6 text-gray-400" />
                                </div>
                                <h3 class="text-xl font-extrabold uppercase text-gray-900 dark:text-white">{{ $group['name'] }}</h3>
                            </div>
                            <div class="text-right">
                                <span class="text-xl font-black" style="color: {{ $pColor }};">
                                    {{ number_format($group['actual'], 2, ',', ' ') }} €
                                </span>
                                <span class="text-[10px] text-gray-400 font-bold uppercase block">z {{ number_format($group['limit'], 2, ',', ' ') }} €</span>
                            </div>
                        </div>

                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-3 overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="h-full transition-all duration-1000" style="width: {{ min($pPercent, 100) }}%; background-color: {{ $pColor }};"></div>
                        </div>
                    </div>

                    {{-- Kategórie (VNÚTRI) ZOSKUUPENÉ PODĽA HLAVNEJ KATEGÓRIE --}}
                    <div x-show="isOpen" x-collapse class="bg-gray-50/50 dark:bg-gray-800/20 p-6 border-t border-gray-100 dark:border-gray-800">
                        
                        @foreach($group['budgets'] as $parentCategoryName => $subcategories)
                            
                            {{-- Hlavička hlavnej kategórie --}}
                            <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3 mt-4 first:mt-0">
                                {{ $parentCategoryName }}
                            </h4>
                            
                            {{-- Grid s podkategóriami Patriacimi pod túto kategóriu --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6 last:mb-0">
                                @foreach($subcategories as $item)
                                    @php
                                        $iPercent = (float) $item['percent'];
                                        $iColor = $iPercent >= 101 ? '#dc2626' : ($iPercent >= 100 ? '#f59e0b' : '#22c55e');
                                    @endphp
                                    <div class="bg-white dark:bg-gray-900 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 border-l-4" style="border-left-color: {{ $iColor }};">
                                        <div class="flex justify-between mb-2">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tight">{{ $item['category'] }}</span>
                                            <span class="font-black text-sm text-gray-900 dark:text-white">{{ number_format($item['actual'], 2, ',', ' ') }} €</span>
                                        </div>
                                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full" style="width: {{ min($iPercent, 100) }}%; background-color: {{ $iColor }};"></div>
                                        </div>
                                        <div class="mt-1 flex justify-between text-[8px] font-bold text-gray-400 uppercase">
                                            <span>{{ round($iPercent, 0) }}%</span>
                                            <span>{{ number_format($item['limit'], 0) }} €</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                        @endforeach
                        
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>