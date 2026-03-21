@php
    $data = $this->getData();
    $pillars = $data['pillars'];
    $topExpenses = $data['top_expenses'];
    $showOverflow = $data['overflow'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                🚀 Operačné centrum: <span class="capitalize">{{ $data['month_name'] }}</span>
            </h2>
            <div class="text-xs text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                Mesačný prehľad limitov
            </div>
        </div>

        {{-- Circles Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 relative">
            @foreach($pillars as $index => $pillar)
                <div class="flex flex-col items-center justify-center space-y-3 relative group">
                    {{-- SVG Progress Circle --}}
                    <div class="relative w-32 h-32" x-data="{ 
                        percent: {{ $pillar['percentage'] }},
                        color: '{{ $pillar['color'] }}',
                        circumference: 2 * Math.PI * 54
                    }">
                        <svg class="w-full h-full transform -rotate-90">
                            {{-- Background Circle --}}
                            <circle
                                cx="64" cy="64" r="54"
                                stroke-width="8" stroke="currentColor"
                                fill="transparent"
                                class="text-gray-200 dark:text-gray-700"
                            />
                            {{-- Progress Circle --}}
                            <circle
                                cx="64" cy="64" r="54"
                                stroke-width="8" stroke="{{ $pillar['color'] }}"
                                stroke-dasharray="339.292"
                                :stroke-dashoffset="339.292 - (percent / 100 * 339.292)"
                                stroke-linecap="round"
                                fill="transparent"
                                class="transition-all duration-1000 ease-out"
                            />
                        </svg>
                        {{-- Center Text --}}
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 capitalize">Ostáva</span>
                            <span class="text-sm font-bold {{ $pillar['remaining'] < 0 ? 'text-danger-600' : 'text-gray-900 dark:text-white' }}">
                                {{ number_format($pillar['remaining'], 0, ',', ' ') }} €
                            </span>
                        </div>
                    </div>

                    {{-- Pillar Name --}}
                    <div class="text-center">
                        <span class="text-sm font-semibold uppercase tracking-wider" style="color: {{ $pillar['color'] }}">
                            {{ $pillar['name'] }}
                        </span>
                        <div class="text-[10px] text-gray-500 mt-1">
                            {{ number_format($pillar['actual_spent'], 0, ',', ' ') }} / {{ number_format($pillar['model_limit'], 0, ',', ' ') }} €
                        </div>
                    </div>

                    {{-- Overflow Indicator (from Pillar 1 to Pillar 3) --}}
                    @if($pillar['is_essential'] && $showOverflow)
                        <div class="hidden lg:block absolute -right-4 top-1/2 -translate-y-1/2 z-10 animate-bounce-h">
                            <svg class="w-8 h-8 text-success-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Top 5 Expenses --}}
        <div class="mt-8">
            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                <x-filament::icon icon="heroicon-m-fire" class="w-4 h-4 text-orange-500" />
                Top 5 výdavkov mesiaca
            </h3>
            <div class="overflow-hidden border border-gray-100 dark:border-gray-800 rounded-xl bg-gray-50/50 dark:bg-gray-800/30">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($topExpenses as $expense)
                            <tr class="hover:bg-white dark:hover:bg-gray-800 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ $expense['date'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full" style="background-color: {{ $expense['color'] }}"></div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[200px]">
                                            {{ $expense['name'] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400 italic">
                                    {{ $expense['category'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-danger-600 dark:text-danger-400">
                                    -{{ number_format($expense['amount'], 2, ',', ' ') }} €
                                </td>
                            </tr>
                        @endforeach
                        @if(empty($topExpenses))
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 italic">
                                    Tento mesiac zatiaľ žiadne výdavky.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<style>
    @keyframes bounce-h {
        0%, 100% { transform: translateX(0) translateY(-50%); }
        50% { transform: translateX(5px) translateY(-50%); }
    }
    .animate-bounce-h {
        animation: bounce-h 1s infinite;
    }
</style>
