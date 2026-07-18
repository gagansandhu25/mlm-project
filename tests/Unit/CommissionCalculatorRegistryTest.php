<?php

namespace Tests\Unit;

use App\Services\Commission\BinaryCommissionCalculator;
use App\Services\Commission\CommissionCalculatorRegistry;
use App\Services\Commission\MatrixCommissionCalculator;
use App\Services\Commission\UnilevelCommissionCalculator;
use Tests\TestCase;

class CommissionCalculatorRegistryTest extends TestCase
{
    public function test_resolves_each_registered_plan_type_to_its_calculator(): void
    {
        $registry = app(CommissionCalculatorRegistry::class);

        $this->assertInstanceOf(UnilevelCommissionCalculator::class, $registry->for('unilevel'));
        $this->assertInstanceOf(BinaryCommissionCalculator::class, $registry->for('binary'));
        $this->assertInstanceOf(MatrixCommissionCalculator::class, $registry->for('matrix'));
    }

    public function test_throws_for_an_unregistered_plan_type(): void
    {
        $registry = app(CommissionCalculatorRegistry::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown plan type [stairstep].');

        $registry->for('stairstep');
    }

    public function test_each_calculator_self_reports_a_plan_type_matching_the_registry_key(): void
    {
        // Guards against a calculator's planType() drifting out of sync
        // with how CommissionServiceProvider/registry expect to key it.
        $registry = new CommissionCalculatorRegistry([
            $unilevel = app(UnilevelCommissionCalculator::class),
        ]);

        $this->assertSame($unilevel, $registry->for($unilevel->planType()));
    }
}
