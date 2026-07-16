# TODO

- Lock `active_plan_type` (system_settings) once install is complete. Currently
  it can be edited anytime post-install via the generic SystemSettingResource
  Filament CRUD (`app/Filament/Resources/SystemSettingResource.php`), which
  edits any setting's raw value with no guard rails. Decision: the plan type
  is chosen once during the install wizard and must not change afterwards.
  Needs: prevent editing/deleting the `active_plan_type` row after install
  (e.g. hide it from the generic Settings CRUD, or make the resource reject
  edits to that specific key once `installed` is true).

- Add binary-specific rank qualification (balanced-leg rule). Currently
  `RankService::evaluate()` (`app/Services/RankService.php`) uses the same
  generic criteria for all plan types: total downline sales_volume (both legs
  combined via `TreeService::getTeamVolume`, no left/right distinction) plus
  `min_downline` count. Real binary plans typically also require the weaker
  leg to independently carry a minimum volume before ranking up, to stop a
  member from stacking all volume on one leg to rank-farm. Needs: a
  binary-aware qualification check (e.g. using `left_volume`/`right_volume`
  or leg totals) used instead of/alongside the generic one when
  `active_plan_type = binary`.

- Cap unilevel sponsor-chain depth at placement time. `UnilevelPlacementStrategy`
  (`app/Services/Placement/UnilevelPlacementStrategy.php`) has no depth logic —
  a recruit always goes directly under their sponsor, unbounded. Binary/matrix
  stay shallow by construction (breadth-first spillover, O(log N) depth), but
  unilevel doesn't, so a long real-world sponsor chain could make one branch
  of `tree_paths` grow toward the pathological O(N^2) case (worst case at 1M
  users: ~500B rows / ~50TB, vs. ~20M rows / ~2GB for a balanced binary tree
  of the same size). Needs: reject or reroute placement beyond a configured
  max depth (e.g. 25-50 levels) in `findPlacement()`.

- Cache `team_volume`/`total_downline` as counter columns on `users` instead
  of computing them live via `TreeService::getTeamVolume`/`getTotalDownline`
  (closure-table joins over the full downline). These are read on every
  commission calculation and rank evaluation (`RankService::evaluate`,
  called from every commission calculator) but only change on
  placement/order events, so they're a good fit for incrementally-maintained
  counters bumped during the ancestor walk `placeNewUser` and the commission
  calculators already do. Turns an O(descendant_count) read into O(1),
  independent of how large or deep `tree_paths` gets — the general-purpose
  fix for read cost regardless of tree shape, complementary to the depth cap
  above rather than a replacement for it.
