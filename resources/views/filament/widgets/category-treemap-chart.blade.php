<div class="fi-wi-widget flex flex-col gap-y-4 overflow-hidden" 
    x-data="{ 
        chart: null,
        total: @js($chartData['total']),
        series: @js($chartData['series']),
        colors: @js($chartData['colors']),

        init() {
            setTimeout(() => {
                this.renderChart();
            }, 50);
            
            document.addEventListener('livewire:initialized', () => {
                if (this.chart) this.chart.destroy();
                setTimeout(() => {
                    this.renderChart();
                }, 50);
            });

            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (this.chart) {
                        this.chart.windowResize();
                    }
                }, 200);
            });
        },

        renderChart() {
            const el = this.$refs.canvas;
            if (!el) return;
            
            // Vyčistíme kontajner, aby sa predišlo duplicate chart bugu pri navrate / update
            el.innerHTML = '';

            const options = {
                series: this.series,
                legend: { show: false },
                chart: {
                    height: 500,
                    type: 'treemap',
                    toolbar: { show: false },
                    animations: { enabled: true },
                    redrawOnWindowResize: true,
                },
                colors: this.colors, // Apply mapped colors for each series
                dataLabels: {
                    enabled: true,
                    textAnchor: 'middle',
                    style: {
                        fontSize: '13px',
                        fontWeight: '900',
                        fontFamily: 'Inter, ui-sans-serif, system-ui',
                    },
                },
                plotOptions: {
                    treemap: {
                        enableShades: true,
                        shadeIntensity: 0.5,
                        reverseNegativeShade: true,
                        borderRadius: 4,
                        useFillColorAsStroke: false
                    }
                },
                tooltip: {
                    theme: 'dark',
                    style: { fontSize: '12px' },
                    y: {
                        formatter: function(val, { series, seriesIndex, dataPointIndex, w }) {
                            const item = w.config.series[seriesIndex].data[dataPointIndex];
                            const formattedVal = new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 }).format(val);
                            
                            if (item && item.limit > 0) {
                                const remaining = item.limit - val;
                                const status = remaining >= 0 ? 'Zostáva: ' : 'Prekročené o: ';
                                const formattedLimit = new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(item.limit);
                                const formattedRem = new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(Math.abs(remaining));
                                
                                return `${formattedVal} / ${formattedLimit}<br><small>${status}${formattedRem}</small>`;
                            }
                            
                            return formattedVal;
                        }
                    }
                },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['#ffffff']
                }
            };

            this.chart = new ApexCharts(el, options);
            this.chart.render();
        }
    }">
    <div wire:key="category-treemap-{{ $selectedPeriod }}" class="fi-wi-stats-overview-card relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 h-[600px] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between mb-4 gap-4">
            <div class="flex items-center gap-3">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">Výdavky podľa kategórií</div>
            </div>
            <div class="flex items-center gap-3">
                <select wire:model.live="selectedPeriod" class="text-xs bg-white border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white pointer-events-auto">
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if(empty($chartData['series']))
            <div class="grow flex items-center justify-center text-sm text-gray-500">
                Žiadne výdavky v tomto období
            </div>
        @else
            <div class="flex flex-col grow">
                <div class="w-full flex-shrink-0" wire:ignore>
                    <div x-ref="canvas" class="w-full h-[400px]"></div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Rozpis kategórií</div>
                        <div class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            Spolu: {{ number_format($chartData['total'], 0, ',', ' ') }} €
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-8 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                        <template x-for="(s, index) in series" :key="s.name">
                            <div class="bg-gray-50/50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-100 dark:border-gray-700/50 flex-[1_1_440px] min-w-[440px]">
                                <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center">
                                        <span class="w-3.5 h-3.5 rounded-full shrink-0 shadow-sm mr-5" :style="'background-color: ' + colors[index]"></span>
                                        <div class="text-[15px] font-bold text-gray-800 dark:text-gray-200 truncate pl-1" x-text="s.name"></div>
                                    </div>
                                    <template x-if="s.limit > 0">
                                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-2">
                                            Limit: <span x-text="new Intl.NumberFormat('sk-SK').format(s.limit) + ' €'"></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="space-y-2 pt-1">
                                    <template x-for="item in s.data" :key="item.x">
                                        <div class="flex items-center justify-between group p-1 -mx-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                                            <div class="flex items-center truncate">
                                                <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-sm opacity-90 mr-4" :style="'background-color: ' + item.fillColor"></span>
                                                <span class="text-[13px] text-gray-600 dark:text-gray-400 truncate pl-1" x-text="item.x"></span>
                                            </div>
                                            <div class="flex items-center gap-3 text-right justify-end shrink-0 pl-4">
                                                <template x-if="item.limit > 0">
                                                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 whitespace-nowrap min-w-[60px]" 
                                                          :class="item.y > item.limit ? 'text-red-500 dark:text-red-400' : ''"
                                                          x-text="'/ ' + new Intl.NumberFormat('sk-SK').format(item.limit) + ' €'"></span>
                                                </template>
                                                <span class="text-xs font-bold text-gray-400 dark:text-gray-500 w-10 text-right" x-text="(item.y / total * 100).toFixed(1) + '%'"></span>
                                                <span class="text-sm text-gray-900 dark:text-gray-300 font-semibold whitespace-nowrap min-w-[60px] text-right" 
                                                      :class="item.limit > 0 && item.y > item.limit ? 'text-red-600 dark:text-red-500' : ''"
                                                      x-text="new Intl.NumberFormat('sk-SK').format(item.y) + ' €'"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        @endif
    </div>
    <style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e5e7eb; border-radius: 20px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #374151; }
    </style>
</div>
