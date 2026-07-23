<?php

namespace App\Services\Modules;

use App\Models\Order;
use App\Models\User;

/**
 * What accrues onto a binary leg, and in what unit — pluggable the same
 * way an ActivePackageResolver is, so a new basis (e.g. a hybrid of
 * volume and count) is a new app/Modules/MatchingBases/{Name}/{Name}Basis.php,
 * never an edit to ConfigurableBinaryMatchingModule. Volume accrues each
 * order's dollar commission_value; Count accrues one qualifying member
 * per leg. capUnit() drives where in ConfigurableBinaryMatchingModule's
 * payout pipeline capping applies: a 'currency' basis's cap ceils the
 * formula's dollar output (after it runs), a 'count' basis's cap ceils
 * how many pairs are even allowed to form (before the formula runs) —
 * see ConfigurableBinaryMatchingModule::matchForUpline() for why these
 * are genuinely different pipeline stages, not just different units of
 * the same cap.
 */
interface MatchingBasis extends HasSettingsSchema
{
    /** Static for the same reason as PlanModule::key() — see its docblock. */
    public static function key(): string;

    /** Human-facing name for the matching-basis dropdown. */
    public function label(): string;

    /** e.g. '$' or 'members' — used on this basis's own settings fields. */
    public function unitLabel(): string;

    /** @return array{0: string, 1: string} [leftColumn, rightColumn] on `users` this basis accrues onto. */
    public function legColumns(): array;

    /** Amount, in this basis's own unit, a qualifying $order adds to the buyer's leg. */
    public function creditAmount(Order $order): float;

    /** Size of one discrete matched pair, in this basis's own unit. */
    public function pairUnitSize(): float;

    /** 'currency' or 'count' — see this interface's own docblock. */
    public function capUnit(): string;

    /** Remaining room $user has left in $period ('daily'|'lifetime'), in this basis's own capUnit(). */
    public function remainingRoom(User $user, string $period): float;
}
