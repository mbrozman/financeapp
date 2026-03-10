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
        $today = now()->copy()->startOfDay();

        // 1. Nájdeme všetky aktívne platby, ktoré majú dátum splatnosti dnes alebo v minulosti
        $recurring = RecurringTransaction::where('is_active', true)
            ->where('next_date', '<=', $today->toDateString())
            ->get();

        foreach ($recurring as $item) {
            // Zabezpečíme dobehnutie zameškaných platieb (napr. ak cron nebežal niekoľko dní)
            while ($item->next_date->startOfDay()->lte($today)) {
                
                // 2. Vytvoríme reálnu transakciu manuálne (nie .create()) kvôli zabezpečeniu užívateľa
                // a správnemu poradiu priraďovania atribútov pre mutátor sumy
                $transaction = new Transaction();
                
                // Najprv priradíme typ, aby mutátor na 'amount' vedel, aké znamienko použiť
                $transaction->type = $item->type;
                $transaction->amount = $item->amount;
                
                // Ostatné atribúty (user_id by sa pri ::create ignorovalo kvôli $fillable)
                $transaction->user_id = $item->user_id;
                $transaction->account_id = $item->account_id;
                $transaction->category_id = $item->category_id;
                $transaction->description = "Automatická platba: {$item->name}";
                $transaction->transaction_date = $item->next_date->copy(); 
                
                $transaction->save();

                // 3. Posunieme next_date na ďalšie obdobie
                $item->next_date = match ($item->interval) {
                    'daily' => $item->next_date->copy()->addDay(),
                    'weekly' => $item->next_date->copy()->addWeek(),
                    'yearly' => $item->next_date->copy()->addYear(),
                    default => $item->next_date->copy()->addMonth(), // monthly
                };

                // Uložíme model (čím sa updatne next_date aj v DB pre ďalší beh while cyklu)
                $item->save();
            }
        }
    }
}
