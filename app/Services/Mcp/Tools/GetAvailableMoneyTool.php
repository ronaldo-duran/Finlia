<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

use App\Models\Household;
use App\Models\McpToken;
use App\Models\User;
use App\Services\BudgetCalculatorService;

/**
 * Responde la pregunta fundadora de Finlia: "¿cuánto puedo gastar hoy sin
 * comprometer mis obligaciones?" (ADR-0040, ADR-0047). Reusa íntegramente
 * BudgetCalculatorService::liquidity para mantener una sola definición de la
 * cifra entre web y MCP.
 */
class GetAvailableMoneyTool implements McpTool
{
    public function __construct(private readonly BudgetCalculatorService $budgets) {}

    public function name(): string
    {
        return 'get_available_money';
    }

    public function title(): string
    {
        return 'Dinero disponible hoy';
    }

    public function description(): string
    {
        return 'Devuelve el cupo diario del hogar activo: cuánto puedes gastar hoy sin comprometer obligaciones fijas, deudas, ahorro programado ni sobres.';
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
        $liquidity = $this->budgets->liquidity($household->id);

        return [
            'household' => $household->name,
            'status' => $liquidity['status'],
            'daily_allowance' => $liquidity['daily_allowance'],
            'daily_allowance_formatted' => money($liquidity['daily_allowance']),
            'daily_target' => $liquidity['daily_target'],
            'spent_today' => $liquidity['spent_today'],
            'cash_available' => $liquidity['cash_available'],
            'days_until_payday' => $liquidity['days'],
            'payday' => $liquidity['payday']->toDateString(),
            'payday_known' => $liquidity['payday_known'],
            'reserved' => $liquidity['reserved'],
            'plan_available' => $liquidity['plan_available'],
            'limited_by' => $liquidity['limited_by'],
        ];
    }
}
