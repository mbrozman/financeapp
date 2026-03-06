<x-filament-panels::page>
    {{-- 1. Ovládací panel (Prepínač mesiacov) --}}
    <div class="flex items-center justify-between bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
        <x-filament::button wire:click="previousMonth" color="gray" icon="heroicon-m-chevron-left" outlined>
            Predošlý mesiac
        </x-filament::button>

        <div class="text-center">
            <h2 class="text-2xl font-black tracking-tight">
                {{ \Carbon\Carbon::parse($selectedMonth . '-01')->translatedFormat('F Y') }}
            </h2>
            @php $data = $this->getBudgetData(); @endphp
            <p class="text-gray-500 text-sm">Príjem v tomto mesiaci: <strong>{{ number_format($data['income'], 2, ',', ' ') }} €</strong></p>
        </div>

        <x-filament::button wire:click="nextMonth" color="gray" icon="heroicon-m-chevron-right" icon-position="after" outlined>
            Nasledujúci mesiac
        </x-filament::button>
    </div>

    {{-- 2. Zoznam rozpočtov (Grid) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">
        @forelse($data['items'] as $item)
            <div class="bg-white dark:bg-gray-900 p-5 rounded-2xl shadow-sm border-l-4 {{ $item['percent'] > 100 ? 'border-red-500' : 'border-green-500' }}">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="font-bold text-lg leading-none">{{ $item['category'] }}</h3>
                        <span class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">{{ $item['pillar'] }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block font-black {{ $item['percent'] > 100 ? 'text-red-500' : 'text-gray-700 dark:text-gray-200' }}">
                            {{ number_format($item['actual'], 2, ',', ' ') }} €
                        </span>
                        <span class="text-xs text-gray-400">z limitu {{ number_format($item['limit'], 2, ',', ' ') }} €</span>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2 mb-2">
                    <div class="h-2 rounded-full {{ $item['percent'] > 100 ? 'bg-red-500 animate-pulse' : 'bg-green-500' }}" 
                         style="width: {{ min($item['percent'], 100) }}%"></div>
                </div>
                
                <div class="flex justify-between text-[10px] font-bold uppercase">
                    <span class="{{ $item['percent'] > 100 ? 'text-red-500' : 'text-gray-400' }}">
                        {{ $item['percent'] > 100 ? 'Prekročené o ' . number_format(abs($item['remaining']), 2) . ' €' : 'Zostáva ' . number_format($item['remaining'], 2) . ' €' }}
                    </span>
                    <span class="text-gray-500">{{ round($item['percent'], 1) }}%</span>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-gray-50 dark:bg-gray-800 p-10 text-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                <p class="text-gray-500 italic">Nemáte nastavené žiadne pravidlá rozpočtu. Choďte do sekcie "Pravidlá rozpočtu".</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>