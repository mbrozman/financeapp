<?php

namespace Tests\Feature;

use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_transactions()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $transactions = Transaction::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords($transactions);
    }

    #[Test]
    public function it_can_create_expense()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'account_id' => $account->id,
                'category_id' => $category->id,
                'amount' => 50.00,
                'type' => 'expense',
                'transaction_date' => now()->format('Y-m-d'),
                'description' => 'Grocery shopping',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Grocery shopping',
            'user_id' => $user->id,
            'type' => 'expense',
        ]);
    }

    #[Test]
    public function it_can_create_income()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income']);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'account_id' => $account->id,
                'category_id' => $category->id,
                'amount' => 2000.00,
                'type' => 'income',
                'transaction_date' => now()->format('Y-m-d'),
                'description' => 'Salary',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Salary',
            'user_id' => $user->id,
            'type' => 'income',
        ]);
    }

    #[Test]
    public function it_cannot_see_others_transactions()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $otherUser = User::factory()->create();
        
        $this->actingAs($user);

        $myTransaction = Transaction::factory()->create(['user_id' => $user->id]);
        $otherTransaction = Transaction::factory()->create(['user_id' => $otherUser->id]);

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$myTransaction])
            ->assertCanNotSeeTableRecords([$otherTransaction]);
    }
}
