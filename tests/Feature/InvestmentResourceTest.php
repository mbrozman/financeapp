<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Investment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Filament\Resources\InvestmentResource\Pages\ListInvestments;
use App\Filament\Resources\InvestmentResource\Pages\ViewInvestment;
use PHPUnit\Framework\Attributes\Test;

class InvestmentResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_investments()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $investments = Investment::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::test(ListInvestments::class)
            ->assertCanSeeTableRecords($investments);
    }

    #[Test]
    public function it_can_view_investment_details()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $investment = Investment::factory()->create(['user_id' => $user->id]);

        Livewire::test(ViewInvestment::class, [
            'record' => $investment->getRouteKey(),
        ])
        ->assertOk()
        ->assertSee($investment->name);
    }

    #[Test]
    public function it_can_only_view_own_investments()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $otherUser = User::factory()->create();
        
        $this->actingAs($user);

        $myInvestment = Investment::factory()->create(['user_id' => $user->id]);
        $otherInvestment = Investment::factory()->create(['user_id' => $otherUser->id]);

        Livewire::test(ListInvestments::class)
            ->assertCanSeeTableRecords([$myInvestment])
            ->assertCanNotSeeTableRecords([$otherInvestment]);
    }
}
