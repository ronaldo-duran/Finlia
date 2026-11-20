<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

use App\Enums\BudgetScope;
use App\Models\Household;
use App\Models\McpToken;
use App\Models\User;
use App\Services\BudgetCalculatorService;

/**
 * Plan del mes en curso: ingresos esperados, gastado, comprometido y
 * disponible. Resumen compacto pensado para que un LLM pueda contestar
 * preguntas abiertas ("¿cómo voy este mes?") sin pedir cifra por cifra.
 */
class GetBudgetSummaryTool implements McpTool
{
    public function __construct(private readonly BudgetCalculatorService $budgets) {}

    public function name(): string
    {
        return 'get_budget_summary';
    }

    public function title(): string
    {
        return 'Resumen del presupuesto del mes';
    }

    public function description(): string
    {
        return 'Plan del mes en curso del hogar activo: ingresos esperados, gastado, comprometido en obligaciones y cuánto queda del plan. Incluye el desglose por categoría presupuestada.';
    }

    public function requiredAbility(): string
    {
        return McpToken::ABILITY_READ;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass,
            'additionalProperties' => false,
        ];
    }

    public function run(array $arguments, User $user, Household $household): array
    {
        $summary = $this->budgets->summary($household->id, BudgetScope::Month);

        $categories = collect($summary['categories'] ?? [])
            ->map(fn (array $c): array => [
                'name' => $c['name'],
                'budget' => $c['budget'],
                'spent' => $c['spent'],
                'remaining' => $c['remaining'],
                'percent' => $c['percent'],
                'envelope' => $c['envelope'],
                'level' => $c['level']?->value,
            ])
            ->all();

        return [
            'household' => $household->name,
            'month' => sprintf('%04d-%02d', $summary['year'], $summary['month']),
            'expected_income' => $summary['expected_income'],
            'spent' => $summary['spent'],
            'committed' => $summary['committed'],
            'plan_available' => $summary['plan_available'],
            'budget_defined' => $summary['budget_defined'],
            'budget_remaining' => $summary['budget_remaining'],
            'consumed_percent' => $summary['consumed_percent'],
            'projected_spend' => $summary['projected_spend'],
            'trend' => $summary['trend'],
            'days_total' => $summary['days_total'],
            'days_elapsed' => $summary['days_elapsed'],
            'days_remaining' => $summary['days_remaining'],
            'categories' => $categories,
        ];
    }
}
