    @php
        $model = $getRecord() ?? $record;
        // Získame rok a mesiac z periódy (napr. "2025-03")
        [$year, $month] = explode('-', $model->period);
        
        // Získame všetky príjmové transakcie pre daný mesiac
        $transactions = \App\Models\Transaction::where('user_id', auth()->id())
            ->where('type', 'income')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with('category')
            ->orderBy('transaction_date', 'desc')
            ->get();
    @endphp

    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-3 m-4">
        <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300">Podrobný rozpis príjmov pre {{ \Carbon\Carbon::parse($model->period . '-01')->translatedFormat('F Y') }}</h4>
        
        @if($transactions->isEmpty())
            <p class="text-sm text-gray-500">Žiadne zaznamenané príjmy v tomto mesiaci.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($transactions as $transaction)
                    <li class="py-2 flex justify-between items-center text-sm">
                        <div class="flex flex-col">
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $transaction->category?->name ?? 'Bez kategórie' }}
                                @if($transaction->name)
                                    <span class="text-gray-500 font-normal ml-1">({{ $transaction->name }})</span>
                                @endif
                            </span>
                            <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d.m.Y') }}</span>
                        </div>
                        <span class="font-bold text-green-600 dark:text-green-400">
                            +{{ number_format((float)$transaction->amount, 2, ',', ' ') }} €
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
        
        @php
            $sum = $transactions->sum('amount');
        @endphp
        
        <div class="pt-2 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-sm">
            <span class="font-medium text-gray-700 dark:text-gray-300">Skutočné príjmy v aplikácii:</span>
            <span class="font-bold {{ (float)$sum == (float)$model->amount ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400' }}">
                {{ number_format((float)$sum, 2, ',', ' ') }} €
                @if((float)$sum != (float)$model->amount)
                    <span class="text-xs ml-1 font-normal">(Zadané: {{ number_format((float)$model->amount, 2, ',', ' ') }} €)</span>
                @endif
            </span>
        </div>
    </div>
</div>
