<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AccountResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_accounts()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $accounts = Account::factory()->count(3)->create([
            'user_id' => $user->id,
            'type' => 'bank',
        ]);

        Livewire::test(ListAccounts::class)
            ->assertCanSeeTableRecords($accounts);
    }

    #[Test]
    public function it_can_create_account()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $currency = Currency::factory()->create();

        Livewire::test(CreateAccount::class)
            ->fillForm([
                'name' => 'Test Account',
                'type' => 'bank',
                'currency_id' => $currency->id,
                'balance' => '100.00',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('accounts', [
            'name' => 'Test Account',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_cannot_see_others_accounts()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $otherUser = User::factory()->create();
        
        $this->actingAs($user);

        $myAccount = Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'bank',
        ]);
        $otherAccount = Account::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'bank',
        ]);

        Livewire::test(ListAccounts::class)
            ->assertCanSeeTableRecords([$myAccount])
            ->assertCanNotSeeTableRecords([$otherAccount]);
    }
}
