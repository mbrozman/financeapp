<x-filament-widgets::widget class="col-span-full">
    <div class="flex flex-col sm:flex-row items-center justify-between p-3 bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700 w-full gap-3">
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300 whitespace-nowrap">Zobraziť v mene:</span>
            
            <div class="flex bg-gray-100 dark:bg-gray-900 p-1 rounded-lg gap-1 w-full sm:w-auto overflow-x-auto">
                {{-- Tlačidlo pre Pôvodnú menu --}}
                <a 
                    href="{{ url()->current() . '?' . http_build_query(request()->except(['currency', 'table_currency'])) }}"
                    class="px-4 py-1.5 text-xs font-bold rounded-md transition-all whitespace-nowrap {{ !(request()->query('currency') || request()->query('table_currency')) ? 'bg-white shadow-sm text-primary-600 dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    Pôvodná
                </a>

                @foreach($this->getCurrencies() as $currency)
                    <a 
                        href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except(['currency', 'table_currency']), ['currency' => $currency->code, 'table_currency' => $currency->code])) }}"
                        class="px-4 py-1.5 text-xs font-bold rounded-md transition-all whitespace-nowrap {{ (request()->query('currency') == $currency->code || request()->query('table_currency') == $currency->code) ? 'bg-white shadow-sm text-primary-600 dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                    >
                        {{ $currency->code }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="text-[10px] text-gray-400 italic hidden lg:block bg-gray-50 dark:bg-gray-900/50 px-3 py-1 rounded-full border border-gray-100 dark:border-gray-700">
            💡 Automatický prepočet všetkých čísel a grafov aktuálnym kurzom.
        </div>
    </div>
</x-filament-widgets::widget>
