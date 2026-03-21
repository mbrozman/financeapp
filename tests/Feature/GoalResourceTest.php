<?php

namespace Tests\Feature;

use App\Filament\Resources\GoalResource\Pages\CreateGoal;
use App\Filament\Resources\GoalResource\Pages\ListGoals;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GoalResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_goals()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $goals = Goal::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::test(ListGoals::class)
            ->assertCanSeeTableRecords($goals);
    }

    #[Test]
    public function it_can_create_goal()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(CreateGoal::class)
            ->fillForm([
                'name' => 'New Car',
                'target_amount' => 15000,
                'current_amount' => 500,
                'deadline' => now()->addYear()->format('Y-m-d'),
                'type' => 'saving',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('goals', [
            'name' => 'New Car',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_cannot_see_others_goals()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $otherUser = User::factory()->create();
        
        $this->actingAs($user);

        $myGoal = Goal::factory()->create(['user_id' => $user->id]);
        $otherGoal = Goal::factory()->create(['user_id' => $otherUser->id]);

        Livewire::test(ListGoals::class)
            ->assertCanSeeTableRecords([$myGoal])
            ->assertCanNotSeeTableRecords([$otherGoal]);
    }
}
