<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function () {
            $today = now()->copy()->startOfDay();

            // 1. Nájdeme všetky aktívne platby, ktoré majú dátum splatnosti dnes alebo v minulosti
            $recurring = RecurringTransaction::where('is_active', true)
                ->where('next_date', '<=', $today->toDateString())
                ->lockForUpdate() // Zámok proti súbežnému spracovaniu
                ->get();

            foreach ($recurring as $item) {
                // Zabezpečíme dobehnutie zameškaných platieb (napr. ak cron nebežal niekoľko dní)
                while ($item->next_date->startOfDay()->lte($today)) {
                    
                    if ($item->type === 'transfer') {
                        // PREVOD: Vytvoríme dve transakcie
                        
                        // 1. Výdavok zo zdrojového účtu
                        $out = new Transaction();
                        $out->type = 'expense';
                        $out->amount = $item->amount;
                        $out->user_id = $item->user_id;
                        $out->account_id = $item->account_id;
                        $out->description = "Pravidelný prevod ➜ {$item->toAccount->name}: {$item->name}";
                        $out->transaction_date = $item->next_date->copy();
                        $out->save();

                        // 2. Príjem na cieľový účet
                        $in = new Transaction();
                        $in->type = 'income';
                        $in->amount = $item->amount;
                        $in->user_id = $item->user_id;
                        $in->account_id = $item->to_account_id;
                        $in->description = "Pravidelný prevod z {$item->account->name}: {$item->name}";
                        $in->transaction_date = $item->next_date->copy();
                        $in->save();

                    } else {
                        // BEŽNÁ PLATBA (Príjem/Výdavok)
                        $transaction = new Transaction();
                        $transaction->type = $item->type;
                        $transaction->amount = $item->amount;
                        $transaction->user_id = $item->user_id;
                        $transaction->account_id = $item->account_id;
                        $transaction->category_id = $item->category_id;
                        $transaction->description = "Automatická platba: {$item->name}";
                        $transaction->transaction_date = $item->next_date->copy();
                        $transaction->save();
                    }

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
        });
    }
}
