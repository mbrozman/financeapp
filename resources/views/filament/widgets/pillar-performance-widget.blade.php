<x-filament-widgets::widget>
        @php
            $data = $this->getPillarData();
            $pillars = $data['pillars'] ?? [];
            $summary = $data['summary'] ?? [];
        @endphp

    <x-filament::section>
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-primary-50 dark:bg-primary-950 rounded-lg shadow-sm">
                    <x-heroicon-o-presentation-chart-line class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white leading-tight tracking-tight">Finančný Cockpit</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Monitoring pilierov a efektivita sporenia</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4 bg-gray-50 dark:bg-gray-900/50 p-1.5 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2 px-3">
                    <span class="text-[10px] uppercase font-bold text-gray-400">Mesiac:</span>
                    <input 
                        type="month" 
                        wire:model.live="period"
                        class="block border-none bg-transparent p-0 text-sm font-bold focus:ring-0 dark:text-white"
                    >
                </div>
            </div>
        </div>

        <!-- Savings Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-10">
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-2xl shadow-sm">
                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Príjem</div>
                <div class="text-xl font-black text-gray-900 dark:text-white">{{ number_format($summary['income'], 0, ',', ' ') }} €</div>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-4 rounded-2xl shadow-sm">
                <div class="text-[10px] font-bold text-gray-400 uppercase mb-1">Výdavky</div>
                <div class="text-xl font-black text-danger-600">{{ number_format($summary['total_expenses'], 0, ',', ' ') }} €</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 p-4 rounded-2xl shadow-sm">
                <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Plánované Sporenie</div>
                <div class="text-xl font-black text-gray-700 dark:text-gray-200">{{ number_format($summary['target_savings'], 0, ',', ' ') }} €</div>
            </div>
            <div class="bg-success-50/50 dark:bg-success-900/10 border border-success-100 dark:border-success-900/30 p-4 rounded-2xl shadow-sm">
                <div class="text-[10px] font-bold text-success-600 uppercase mb-1">Skutočné Sporenie</div>
                <div class="text-xl font-black text-success-700 dark:text-success-400">{{ number_format($summary['actual_savings'], 0, ',', ' ') }} €</div>
                @if($summary['savings_surplus'] > 0)
                    <div class="text-[10px] font-bold text-success-600 mt-1 uppercase flex items-center gap-1">
                        <x-heroicon-m-arrow-trending-up class="w-3 h-3" />
                        Extra úspora: +{{ number_format($summary['savings_surplus'], 0, ',', ' ') }} €
                    </div>
                @elseif($summary['savings_surplus'] < 0)
                    <div class="text-[10px] font-bold text-danger-600 mt-1 uppercase flex items-center gap-1">
                        <x-heroicon-m-arrow-trending-down class="w-3 h-3" />
                        Deficit voči plánu: {{ number_format($summary['savings_surplus'], 0, ',', ' ') }} €
                    </div>
                @endif
            </div>
        </div>

        @if(empty($pillars))
            <div class="py-12 text-center">
                <p class="text-sm text-gray-500">Žiadny aktívny finančný plán nenájdený.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                @foreach($pillars as $pillar)
                    @php
                        $max = max($pillar['model_limit'], $pillar['configured_limit'], $pillar['actual_spent'], 1);
                        
                        $pctModel = ($pillar['model_limit'] / $max) * 100;
                        $pctConfig = ($pillar['configured_limit'] / $max) * 100;
                        $pctActual = ($pillar['actual_spent'] / $max) * 100;

                        $isOverLimit = $pillar['actual_spent'] > $pillar['configured_limit'] && $pillar['configured_limit'] > 0;
                        $isOverModel = $pillar['actual_spent'] > $pillar['model_limit'];
                    @endphp
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ $pillar['name'] }}</span>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                {{ $pillar['percentage'] }}% Príjmu
                            </span>
                        </div>

                        <div class="space-y-3">
                            <!-- Modelová čiara -->
                            <div class="space-y-1">
                                <div class="flex justify-between text-[10px] uppercase font-bold text-gray-400 tracking-tighter">
                                    <span>Model (Ideálne)</span>
                                    <span>{{ number_format($pillar['model_limit'], 0, ',', ' ') }} €</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 border border-gray-200 dark:border-gray-700">
                                    <div class="h-full rounded-full bg-gray-400 dark:bg-gray-500 transition-all duration-500" style="width: {{ $pctModel }}%"></div>
                                </div>
                            </div>

                            <!-- Konfigurovaná čiara -->
                            <div class="space-y-1">
                                <div class="flex justify-between text-[10px] uppercase font-bold text-blue-500 tracking-tighter">
                                    <span>Váš Rozpočet</span>
                                    <span>{{ number_format($pillar['configured_limit'], 0, ',', ' ') }} €</span>
                                </div>
                                <div class="w-full bg-blue-50 dark:bg-blue-950/30 rounded-full h-1.5 border border-blue-100 dark:border-blue-900/50">
                                    <div class="h-full rounded-full bg-blue-500 transition-all duration-500" style="width: {{ $pctConfig }}%"></div>
                                </div>
                            </div>

                            <!-- Reálna čiara -->
                            <div class="space-y-1">
                                <div class="flex justify-between text-[10px] uppercase font-bold tracking-tighter {{ $isOverLimit ? 'text-danger-600' : 'text-gray-700 dark:text-gray-200' }}">
                                    <span>Skutočnosť (Realita)</span>
                                    <span>{{ number_format($pillar['actual_spent'], 0, ',', ' ') }} €</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2.5 overflow-hidden shadow-inner">
                                    <div 
                                        class="h-full rounded-full transition-all duration-700 relative" 
                                        style="width: {{ $pctActual }}%; background-color: {{ $isOverLimit ? '#ef4444' : $pillar['color'] }}"
                                    >
                                        @if($isOverLimit)
                                            <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($isOverLimit)
                            <div class="flex items-center gap-1 text-[10px] text-danger-600 font-bold uppercase transition-all animate-bounce">
                                <x-heroicon-m-exclamation-triangle class="w-3 h-3" />
                                Prekročený rozpočet o {{ number_format($pillar['actual_spent'] - $pillar['configured_limit'], 0, ',', ' ') }} €
                            </div>
                        @elseif($pillar['configured_limit'] > 0 && $pillar['actual_spent'] < $pillar['configured_limit'] * 0.8)
                             <div class="text-[10px] text-success-600 font-bold uppercase">
                                Úspora voči rozpočtu: {{ number_format($pillar['configured_limit'] - $pillar['actual_spent'], 0, ',', ' ') }} €
                             </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
