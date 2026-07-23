<?php

namespace Tests\Feature;

use App\Models\Downline;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreeServiceTest extends TestCase
{
    use RefreshDatabase;

    private TreeService $tree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tree = app(TreeService::class);
    }

    private function makeRoot(): User
    {
        $root = User::factory()->create(['depth' => 0]);
        $this->tree->placeRoot($root);

        return $root;
    }

    private function closureDepth(User $ancestor, User $descendant): ?int
    {
        return Downline::query()
            ->where('ancestor_id', $ancestor->id)
            ->where('descendant_id', $descendant->id)
            ->value('depth');
    }

    public function test_unilevel_places_every_recruit_directly_under_their_sponsor(): void
    {
        $root = $this->makeRoot();

        $childA = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $childB = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $grandchild = $this->tree->placeNewUser(User::factory()->make(), $childA, 'unilevel');

        $this->assertSame($root->id, $childA->parent_id);
        $this->assertSame($root->id, $childB->parent_id);
        $this->assertSame($childA->id, $grandchild->parent_id);
        $this->assertSame(1, $this->closureDepth($root, $childA));
        $this->assertSame(1, $this->closureDepth($childA, $grandchild));
        $this->assertSame(2, $this->closureDepth($root, $grandchild));
        $this->assertSame(1, $childA->depth);
        $this->assertSame(2, $grandchild->depth);

        // Unilevel has unlimited width: a third direct recruit still lands on the root.
        $childC = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $this->assertSame($root->id, $childC->parent_id);
    }

    public function test_binary_fills_left_then_right_then_spills_over_breadth_first(): void
    {
        $root = $this->makeRoot();

        $left = $this->tree->placeNewUser(User::factory()->make(), $root, 'binary');
        $right = $this->tree->placeNewUser(User::factory()->make(), $root, 'binary');

        $this->assertSame('left', $left->position);
        $this->assertSame('right', $right->position);
        $this->assertSame($root->id, $left->parent_id);
        $this->assertSame($root->id, $right->parent_id);

        // Root's left/right are both taken now, so the next recruit spills
        // over to the left child's left slot (breadth-first).
        $spillover = $this->tree->placeNewUser(User::factory()->make(), $root, 'binary');
        $this->assertSame($left->id, $spillover->parent_id);
        $this->assertSame('left', $spillover->position);
    }

    public function test_matrix_fills_configured_width_before_spilling_over(): void
    {
        SystemSetting::set('matrix_width', '3');

        $root = $this->makeRoot();

        $c1 = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');
        $c2 = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');
        $c3 = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');

        $this->assertSame($root->id, $c1->parent_id);
        $this->assertSame($root->id, $c2->parent_id);
        $this->assertSame($root->id, $c3->parent_id);
        $this->assertSame(['1', '2', '3'], [$c1->position, $c2->position, $c3->position]);

        // Root's 3 slots are full, so the 4th recruit spills to the first child.
        $spillover = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');
        $this->assertSame($c1->id, $spillover->parent_id);
        $this->assertSame('1', $spillover->position);
    }

    /**
     * Regression test: MatrixPlacementStrategy is registered as a
     * container singleton (via PlacementServiceProvider), so it's
     * constructed once and reused for every placement call. It must
     * read matrix_width fresh inside findPlacement() rather than
     * capturing it in its constructor — otherwise a long-running
     * process would keep placing recruits under a stale width even
     * after an admin changed the setting mid-run.
     */
    public function test_matrix_width_changes_take_effect_without_reconstructing_the_strategy(): void
    {
        SystemSetting::set('matrix_width', '1');

        $root = $this->makeRoot();
        $onlyChild = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');

        // Root's single slot (width 1) is already full, so this recruit
        // would spill over to $onlyChild if the strategy were still
        // using width 1 — but if the setting change below takes effect,
        // it lands directly under $root instead, in root's newly-opened
        // second slot.
        SystemSetting::set('matrix_width', '2');
        $secondChild = $this->tree->placeNewUser(User::factory()->make(), $root, 'matrix');

        $this->assertSame($root->id, $secondChild->parent_id);
        $this->assertSame('2', $secondChild->position);
    }

    public function test_get_ancestors_by_level_returns_closest_ancestor_first(): void
    {
        $root = $this->makeRoot();
        $child = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $grandchild = $this->tree->placeNewUser(User::factory()->make(), $child, 'unilevel');

        $ancestors = $this->tree->getAncestorsByLevel($grandchild, 10);

        $this->assertSame($child->id, $ancestors[1]->id);
        $this->assertSame($root->id, $ancestors[2]->id);
        $this->assertCount(2, $ancestors);
    }

    public function test_get_ancestors_by_level_respects_max_level(): void
    {
        $root = $this->makeRoot();
        $child = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $grandchild = $this->tree->placeNewUser(User::factory()->make(), $child, 'unilevel');

        $ancestors = $this->tree->getAncestorsByLevel($grandchild, 1);

        $this->assertCount(1, $ancestors);
        $this->assertSame($child->id, $ancestors[1]->id);
    }

    public function test_get_ancestors_returns_root_first(): void
    {
        // BinaryPairingCommissionModule relies on getAncestors() being
        // root-first and reverses it to get closest-first — if this
        // ordering ever flips silently, commission would be credited
        // to the wrong side of the tree.
        $root = $this->makeRoot();
        $child = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $grandchild = $this->tree->placeNewUser(User::factory()->make(), $child, 'unilevel');

        $ancestors = $this->tree->getAncestors($grandchild);

        $this->assertSame([$root->id, $child->id], $ancestors->pluck('id')->all());
    }

    public function test_get_descendants_and_total_downline(): void
    {
        $root = $this->makeRoot();
        $child = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $this->tree->placeNewUser(User::factory()->make(), $child, 'unilevel');
        $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        $this->assertSame(3, $this->tree->getTotalDownline($root));
        $this->assertCount(3, $this->tree->getDescendants($root));
        $this->assertCount(1, $this->tree->getDescendants($child));
    }

    public function test_get_team_volume_sums_descendant_sales_volume(): void
    {
        $root = $this->makeRoot();
        $childA = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 100]), $root, 'unilevel');
        $childB = $this->tree->placeNewUser(User::factory()->make(['sales_volume' => 250]), $root, 'unilevel');

        $this->assertSame(350.0, $this->tree->getTeamVolume($root));
    }

    public function test_sibling_rank_orders_children_by_placement_regardless_of_plan_position(): void
    {
        $root = $this->makeRoot();

        $first = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $second = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');
        $third = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        // Unilevel never sets `position` at all — sibling rank has to
        // come from placement order (id), not that column.
        $this->assertNull($first->position);

        $this->assertSame(1, $this->tree->siblingRank($first));
        $this->assertSame(2, $this->tree->siblingRank($second));
        $this->assertSame(3, $this->tree->siblingRank($third));
    }

    public function test_sibling_rank_is_null_for_a_user_with_no_parent(): void
    {
        $root = $this->makeRoot();

        $this->assertNull($this->tree->siblingRank($root));
    }

    public function test_deleting_a_user_cascades_their_closure_rows_in_both_directions(): void
    {
        $root = $this->makeRoot();
        $child = $this->tree->placeNewUser(User::factory()->make(), $root, 'unilevel');

        $this->assertDatabaseHas('downlines', ['ancestor_id' => $root->id, 'descendant_id' => $child->id]);

        $child->delete();

        $this->assertDatabaseMissing('downlines', ['descendant_id' => $child->id]);
        $this->assertDatabaseMissing('downlines', ['ancestor_id' => $child->id]);
    }
}
