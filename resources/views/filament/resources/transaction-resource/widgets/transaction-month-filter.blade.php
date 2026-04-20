<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="height: 100%;">
        <div class="flex flex-col h-full justify-center">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                Zvolené obdobie
            </div>
            
            <div class="flex items-center justify-between gap-x-3 w-full">
                <div class="flex-1 min-w-0">
                    <form wire:submit="updateFilter">
                        {{ $this->form }}
                    </form>
                </div>
                <div class="flex items-center">
                    <button 
                        type="button"
                        wire:click="resetFilter" 
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-50 text-gray-400 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-500 dark:hover:bg-white/10 transition-colors border border-gray-100 dark:border-white/5"
                        title="Zrušiť filter"
                    >
                        <x-heroicon-m-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
