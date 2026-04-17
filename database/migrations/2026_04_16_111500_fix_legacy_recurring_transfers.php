<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Vyhľadáme všetky transakcie, ktoré boli vytvorené automatom ako prevody
        $transactions = DB::table('transactions')
            ->where('description', 'like', 'Pravidelný prevod%')
            ->get();

        foreach ($transactions as $t1) {
            // Ak už je prepojený, len skontrolujeme typ
            if ($t1->linked_transaction_id) {
                DB::table('transactions')->where('id', $t1->id)->update(['type' => 'transfer']);
                continue;
            }

            // Pokúsime sa nájsť druhú stranu prevodu (rovnaký dátum, rovnaký používateľ, opačná suma)
            $t2 = DB::table('transactions')
                ->where('user_id', $t1->user_id)
                ->where('transaction_date', $t1->transaction_date)
                ->where('amount', '=', -$t1->amount)
                ->where('id', '!=', $t1->id)
                ->where('description', 'like', 'Pravidelný prevod%')
                ->whereNull('linked_transaction_id')
                ->first();

            if ($t2) {
                // Prepojíme ich a zmeníme typ na transfer
                DB::table('transactions')->where('id', $t1->id)->update([
                    'type' => 'transfer',
                    'linked_transaction_id' => $t2->id
                ]);
                DB::table('transactions')->where('id', $t2->id)->update([
                    'type' => 'transfer',
                    'linked_transaction_id' => $t1->id
                ]);
            } else {
                // Ak nenájdeme pár (čo by sa nemalo stávať, ale pre istotu), len zmeníme typ
                DB::table('transactions')->where('id', $t1->id)->update(['type' => 'transfer']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Návrat do pôvodného stavu by bol rizikový (nie je jasné, čo bol income a čo expense bez prepočítavania),
        // preto v down() len necháme typy na 'transfer', čo ničomu neškodí.
    }
};
