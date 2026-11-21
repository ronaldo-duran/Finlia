<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

use App\Models\Expense;
use App\Models\Household;
use App\Models\Income;
use App\Models\McpToken;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Últimos movimientos del hogar activo: ingresos y gastos ordenados por
 * fecha descendente. La ventana se acota a los últimos 60 días para evitar
 * payloads grandes y dar al LLM un marco temporal predecible.
 */
class ListRecentMovementsTool implements McpTool
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    private const WINDOW_DAYS = 60;

    public function name(): string
    {
        return 'list_recent_movements';
    }

    public function title(): string
    {
        return 'Movimientos recientes';
    }

    public function description(): string
    {
        return 'Devuelve los últimos movimientos (ingresos y gastos) del hogar activo en los últimos 60 días. Útil para responder "¿en qué me gasté la plata?".';
    }

    public function requiredAbility(): string
    {
        return McpToken::ABILITY_READ;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Cuántos movimientos devolver. Por defecto '.self::DEFAULT_LIMIT.', máximo '.self::MAX_LIMIT.'.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['expense', 'income', 'all'],
                    'description' => 'Filtrar por tipo. Por defecto "all".',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function run(array $arguments, User $user, Household $household): array
    {
        $limit = (int) ($arguments['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $type = $arguments['type'] ?? 'all';

        $since = Carbon::now()->subDays(self::WINDOW_DAYS)->toDateString();

        $expenses = $type === 'income' ? collect() : Expense::query()
            ->where('household_id', $household->id)
            ->where('date', '>=', $since)
            ->with(['category:id,name', 'account:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Expense $e): array => [
                'type' => 'expense',
                'id' => $e->id,
                'date' => $e->date?->toDateString(),
                'amount' => (float) $e->amount,
                'amount_formatted' => money($e->amount),
                'description' => $e->description,
                'category' => $e->category?->name,
                'account' => $e->account?->name,
            ]);

        $incomes = $type === 'expense' ? collect() : Income::query()
            ->where('household_id', $household->id)
            ->where('date', '>=', $since)
            ->with(['category:id,name', 'account:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Income $i): array => [
                'type' => 'income',
                'id' => $i->id,
                'date' => $i->date?->toDateString(),
                'amount' => (float) $i->amount,
                'amount_formatted' => money($i->amount),
                'description' => $i->description,
                'category' => $i->category?->name,
                'account' => $i->account?->name,
            ]);

        $movements = $expenses->concat($incomes)
            ->sortByDesc(fn (array $m): string => ($m['date'] ?? '0000-00-00').sprintf('%010d', $m['id']))
            ->take($limit)
            ->values()
            ->all();

        return [
            'household' => $household->name,
            'window_days' => self::WINDOW_DAYS,
            'count' => count($movements),
            'movements' => $movements,
        ];
    }
}
