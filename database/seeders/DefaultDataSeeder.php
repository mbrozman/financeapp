<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Zabezpečíme meny
        Currency::updateOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'symbol' => '€',
            'exchange_rate' => 1.0,
        ]);

        Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.08,
        ]);

        // 2. Nájdeme všetkých užívateľov a inicializujeme ich cez Service
        $users = User::all();
        $service = app(\App\Services\UserInitializationService::class);

        foreach ($users as $user) {
            $service->initialize($user);
        }
    }
}
