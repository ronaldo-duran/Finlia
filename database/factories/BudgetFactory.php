<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BudgetPeriod;
use App\Models\Budget;
use App\Models\Household;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 *
 * Por defecto genera el presupuesto TOTAL (category_id null) del mes actual.
 * Para uno por categoría, usa ->forCategory($category).
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = Carbon::now(config('app.timezone'));

        return [
            'household_id' => Household::factory(),
            'category_id' => null,
            'amount' => fake()->numberBetween(2000, 30000) * 100, // montos COP realistas
            'period' => BudgetPeriod::Monthly->value,
            'year' => $now->year,
            'month' => $now->month,
        ];
    }

    /**
     * Presupuesto de una categoría concreta.
     */
    public function forCategory(int $categoryId): static
    {
        return $this->state(fn () => ['category_id' => $categoryId]);
    }

    /**
     * Presupuesto de un mes concreto.
     */
    public function forMonth(int $year, int $month): static
    {
        return $this->state(fn () => ['year' => $year, 'month' => $month]);
    }
}
