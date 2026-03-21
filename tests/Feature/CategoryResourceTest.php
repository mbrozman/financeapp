<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_categories()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $categories = Category::factory()->count(3)->create([
            'user_id' => $user->id,
            'parent_id' => null,
        ]);

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords($categories);
    }

    #[Test]
    public function it_can_create_category()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Food',
                'type' => 'expense',
                'color' => '#ef4444',
                'icon' => 'heroicon-o-shopping-cart',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Food',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_can_create_subcategory()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($user);

        $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Housing']);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Rent',
                'parent_id' => $parent->id,
                'type' => 'expense',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Rent',
            'parent_id' => $parent->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_cannot_see_others_categories()
    {
        $user = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $otherUser = User::factory()->create();
        
        $this->actingAs($user);

        $myCategory = Category::factory()->create([
            'user_id' => $user->id,
            'parent_id' => null,
        ]);
        $otherCategory = Category::factory()->create([
            'user_id' => $otherUser->id,
            'parent_id' => null,
        ]);

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$myCategory])
            ->assertCanNotSeeTableRecords([$otherCategory]);
    }
}
