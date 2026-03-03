<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-recurring-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Nájdeme všetky aktívne platby, ktoré majú dátum splatnosti dnes alebo v minulosti
        $recurring = RecurringTransaction::where('is_active', true)
            ->where('next_date', '<=', now()->toDateString())
            ->get();

        foreach ($recurring as $item) {
            // 2. Vytvoríme reálnu transakciu
            Transaction::create([
                'user_id' => $item->user_id,
                'account_id' => $item->account_id,
                'category_id' => $item->category_id,
                'amount' => $item->amount,
                'type' => $item->type,
                'description' => "Automatická platba: {$item->name}",
                'transaction_date' => $item->next_date,
            ]);

            // 3. Posunieme next_date na ďalšie obdobie
            $nextDate = match ($item->interval) {
                'weekly' => $item->next_date->addWeek(),
                'yearly' => $item->next_date->addYear(),
                default => $item->next_date->addMonth(), // monthly
            };

            $item->update(['next_date' => $nextDate]);
        }
    }
}
