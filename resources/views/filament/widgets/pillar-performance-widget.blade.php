<div>
@php
    $pillars = $data['pillars'] ?? [];
    $surplus = $data['surplus'] ?? ['total' => 0, 'extra_income' => 0, 'pillar_savings' => 0];
@endphp

{{-- Load ApexCharts eagerly in <head> --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3" defer></script>
@endpush

<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4" style="margin-bottom: 2rem;">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div>
                    <h2 class="text-base font-extrabold text-gray-900 dark:text-white tracking-tight">Finančný Cockpit</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Čerpanie alokácie v aktuálnom mesiaci</p>
                </div>

                {{-- Surplus Badge --}}
                @if($surplus['total'] > 0)
                <div class="flex items-center gap-2 bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded-full px-4 py-1.5 shadow-sm"
                     title="Nadvýnos: {{ number_format($surplus['extra_income'], 2, ',', ' ') }} € + Úspora v pilieroch: {{ number_format($surplus['pillar_savings'], 2, ',', ' ') }} €">
                    <x-heroicon-m-plus-circle class="w-4 h-4 text-success-600" />
                    <span class="text-xs font-black text-success-700 dark:text-success-400">
                        Plus {{ number_format($surplus['total'], 0, ',', ' ') }} €
                    </span>
                </div>
                @endif
            </div>

            <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2">
                <span class="text-[10px] uppercase font-bold text-gray-400">Mesiac:</span>
                <input type="month" wire:model.live="period"
                    class="border-none bg-transparent p-0 text-sm font-semibold focus:ring-0 dark:text-white">
            </div>
        </div>

        @if(empty($pillars))
            <div class="py-10 text-center text-sm text-gray-400">Žiadny aktívny finančný plán.</div>
        @else
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-5">
            @foreach($pillars as $i => $pillar)
                @php
                    $spent     = $pillar['actual_spent'];
                    $alloc     = $pillar['allocated_limit'];
                    $isSaving  = $pillar['is_saving'] ?? false;
                    $remaining = max(0, $alloc - $spent);
                    $pct       = $alloc > 0 ? round(min(100, ($spent / $alloc) * 100)) : 0;
                    
                    // Logic for colors: Use pillar color as base, change only for status feedback
                    if ($isSaving) {
                        $isSuccess = $spent >= $alloc;
                        $arcColor  = $isSuccess ? '#228b22' : ($pillar['color'] ?? '#87ceeb');
                    } else {
                        $isOver    = $spent > $alloc;
                        $arcColor  = $isOver ? '#ff0000' : ($pillar['color'] ?? '#ff0000');
                    }
                    
                    $chartId   = 'pchart-' . $i . '-' . md5($this->period);
                @endphp

                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-4 flex flex-col items-center shadow-sm"
                     wire:key="pillar-card-{{ $i }}-{{ $this->period }}"
                     x-data="{
                         chart: null,
                         renderChart() {
                             const el = document.getElementById('{{ $chartId }}');
                             if (!el) return;
                             if (this.chart) { try { this.chart.destroy(); } catch(e){} this.chart = null; }
                             el.innerHTML = '';
                             const isDark = document.documentElement.classList.contains('dark');
                             const opts = {
                                 series: [{{ $pct }}],
                                 chart: {
                                     type: 'radialBar',
                                     height: 180,
                                     background: 'transparent',
                                     toolbar: { show: false },
                                     sparkline: { enabled: true },
                                     animations: { enabled: true, speed: 600 },
                                 },
                                 plotOptions: {
                                     radialBar: {
                                         startAngle: -130,
                                         endAngle: 130,
                                         hollow: { size: '60%', background: 'transparent' },
                                         track: { background: isDark ? '#1f2937' : '#f3f4f6', strokeWidth: '100%' },
                                         dataLabels: {
                                             show: true,
                                             name: {
                                                 show: true, fontSize: '10px', fontWeight: 700,
                                                 color: isDark ? '#9ca3af' : '#6b7280',
                                                 offsetY: 20,
                                                 formatter: () => 'ostáva'
                                             },
                                             value: {
                                                 show: true, fontSize: '14px', fontWeight: 900,
                                                 color: isDark ? '#ffffff' : '#111827',
                                                 offsetY: -8,
                                                 formatter: () => '{{ number_format($remaining, 0, ",", " ") }} €'
                                             },
                                         },
                                     },
                                 },
                                 colors: ['{{ $arcColor }}'],
                                 stroke: { lineCap: 'round' },
                             };
                             this.chart = new ApexCharts(el, opts);
                             this.chart.render();
                         },
                         waitAndRender() {
                             if (window.ApexCharts) {
                                 this.renderChart();
                             } else {
                                 setTimeout(() => this.waitAndRender(), 100);
                             }
                         },
                         init() { this.$nextTick(() => this.waitAndRender()); },
                         destroy() { if (this.chart) { try { this.chart.destroy(); } catch(e){} this.chart = null; } }
                     }" x-init="init()">

                    {{-- Chart --}}
                    <div id="{{ $chartId }}" class="w-full"></div>

                    <div class="text-center mt-1 mb-3">
                        <span class="text-[11px] font-black uppercase tracking-widest" style="color: {{ $pillar['color'] }}">
                            {{ $loop->iteration }}. {{ $pillar['name'] }}
                        </span>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $pillar['percentage'] }}% príjmu</p>
                    </div>

                    {{-- Mini stats --}}
                    <div class="w-full border-t border-gray-100 dark:border-gray-800 pt-3 space-y-1.5">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-gray-400">Minuté</span>
                            <span class="text-xs font-bold {{ $isOver ? 'text-danger-600' : 'text-gray-700 dark:text-gray-200' }}">
                                {{ number_format($spent, 0, ',', ' ') }} €
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-gray-400">Alokácia</span>
                            <span class="text-xs font-semibold text-blue-500">{{ number_format($alloc, 0, ',', ' ') }} €</span>
                        </div>
                    </div>

                    {{-- Status badge --}}
                    <div class="mt-3 w-full">
                        @if($isSaving)
                            @if($spent >= $alloc)
                                <div class="text-center text-[10px] bg-success-50 dark:bg-success-900/20 text-success-700 font-bold uppercase px-2 py-1 rounded-lg">
                                    🚀 Cieľ splnený (+{{ number_format($spent - $alloc, 0, ',', ' ') }} €)
                                </div>
                            @else
                                <div class="text-center text-[10px] bg-warning-50 dark:bg-warning-900/20 text-warning-700 font-bold uppercase px-2 py-1 rounded-lg">
                                    ⏳ Chýba {{ number_format($alloc - $spent, 0, ',', ' ') }} € do cieľa
                                </div>
                            @endif
                        @else
                            @if($spent > $alloc)
                                <div class="text-center text-[10px] bg-danger-50 dark:bg-danger-900/20 text-danger-600 font-bold uppercase px-2 py-1 rounded-lg">
                                    ⚠ Prečerpanie {{ number_format($spent - $alloc, 0, ',', ' ') }} €
                                </div>
                            @else
                                <div class="text-center text-[10px] bg-success-50 dark:bg-success-900/20 text-success-700 font-bold uppercase px-2 py-1 rounded-lg">
                                    ✓ Úspora {{ number_format($remaining, 0, ',', ' ') }} €
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Ensure ApexCharts is available --}}
        <script>
            (function() {
                if (!window.ApexCharts && !document.querySelector('script[data-apexcharts]')) {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3';
                    s.setAttribute('data-apexcharts', '1');
                    document.head.appendChild(s);
                }
            })();
        </script>
    </x-filament::section>
</x-filament-widgets::widget>
</div>
