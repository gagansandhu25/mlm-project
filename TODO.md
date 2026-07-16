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
