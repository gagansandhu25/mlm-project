<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Genealogy;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GenealogyTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tree = app(TreeService::class);
    }

    private function root(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $root->path = (string) $root->id;
        $root->save();

        return $root;
    }

    public function test_member_sees_their_own_stats_and_direct_downline_by_default(): void
    {
        $me = $this->root();
        $me->forceFill(['sales_volume' => 100])->save();

        $child = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 50]), $me, 'unilevel');

        Livewire::actingAs($me)
            ->test(Genealogy::class)
            ->assertSee($me->name)
            ->assertSee($child->name)
            ->assertSet('focusUserId', null);
    }

    public function test_clicking_into_a_child_recenters_the_view_on_them(): void
    {
        $me = $this->root();
        $child = $this->tree->placeNewUser(User::factory()->make(), $me, 'unilevel');
        $grandchild = $this->tree->placeNewUser(User::factory()->make(), $child, 'unilevel');

        $component = Livewire::actingAs($me)
            ->test(Genealogy::class)
            ->call('focus', $child->id);

        $component->assertSet('focusUserId', $child->id)
            ->assertSee($grandchild->name)
            ->assertSee($child->name);
    }

    public function test_cannot_focus_on_a_user_outside_their_own_downline(): void
    {
        $me = $this->root();
        $stranger = $this->root(); // a separate, unrelated root

        Livewire::actingAs($me)
            ->test(Genealogy::class)
            ->call('focus', $stranger->id)
            ->assertSet('focusUserId', null); // silently reset to self, not the stranger
    }

    public function test_breadcrumb_trail_reflects_the_navigation_path(): void
    {
        $me = $this->root();
        $child = $this->tree->placeNewUser(User::factory()->make(['name' => 'Child Member']), $me, 'unilevel');
        $grandchild = $this->tree->placeNewUser(User::factory()->make(['name' => 'Grandchild Member']), $child, 'unilevel');

        Livewire::actingAs($me)
            ->test(Genealogy::class)
            ->call('focus', $grandchild->id)
            ->assertSee('Child Member')
            ->assertSee('Grandchild Member');
    }

    public function test_leg_counts_and_volume_reflect_binary_placement(): void
    {
        $me = $this->root();

        $left = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 30]), $me, 'binary');
        $right = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 70]), $me, 'binary');

        $this->assertSame(User::POSITION_LEFT, $left->position);
        $this->assertSame(User::POSITION_RIGHT, $right->position);

        Livewire::actingAs($me)
            ->test(Genealogy::class)
            ->assertSee('30.00') // left leg volume
            ->assertSee('70.00'); // right leg volume
    }
}
