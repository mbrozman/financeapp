<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@admin.sk'],
            [
                'name' => 'Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('49492137'),
                'is_superadmin' => true,
                'is_active' => true,
            ]
        );

        $this->call([
            DefaultDataSeeder::class,
        ]);
    }
}
