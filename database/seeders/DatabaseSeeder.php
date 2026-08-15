<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\HouseholdRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\HouseholdInvitation;
use App\Models\Income;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\HouseholdService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed de la aplicación con datos FALSOS de demostración.
     * NUNCA usar datos financieros reales aquí.
     */
    public function run(): void
    {
        // Categorías globales (catálogo, no datos financieros).
        $this->call(CategorySeeder::class);

        // Usuario de demostración para desarrollo local.
        $demo = User::factory()->create([
            'name' => 'Usuario Demo Finlia',
            'email' => 'demo@finlia.test',
            'password' => 'finlia123',
        ]);

        // Hogar principal del usuario demo.
        $household = app(HouseholdService::class)->createHousehold(
            ownerId: $demo->id,
            name: 'Hogar Demo',
        );

        // Segundo usuario invitado como miembro.
        $miembro = User::factory()->create([
            'name' => 'Miembro Demo',
            'email' => 'miembro@finlia.test',
            'password' => 'finlia123',
        ]);
        $household->members()->attach($miembro->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now()->subDays(3),
        ]);

        // Una invitación pendiente de ejemplo (token hasheado).
        HouseholdInvitation::factory()->create([
            'household_id' => $household->id,
            'email' => 'invitado@finlia.test',
        ]);

        $this->seedFinances($household, $demo, $miembro);
    }

    /**
     * Crea cuentas, ingresos y gastos FALSOS para el hogar demo y deja los
     * saldos coherentes (recomputados desde los movimientos, ADR-0012).
     */
    private function seedFinances(object $household, User $demo, User $miembro): void
    {
        $accounts = collect([
            ['name' => 'Efectivo', 'type' => AccountType::Cash->value, 'initial_balance' => 200000],
            ['name' => 'Bancolombia', 'type' => AccountType::Bank->value, 'initial_balance' => 1500000],
            ['name' => 'Nequi', 'type' => AccountType::DigitalWallet->value, 'initial_balance' => 80000],
        ])->map(fn (array $a) => Account::create([
            'household_id' => $household->id,
            'name' => $a['name'],
            'type' => $a['type'],
            'initial_balance' => $a['initial_balance'],
            'current_balance' => $a['initial_balance'],
            'currency' => 'COP',
        ]));

        $incomeCategories = Category::whereNull('household_id')->where('type', 'income')->pluck('id');
        $expenseCategories = Category::whereNull('household_id')->where('type', 'expense')->pluck('id');
        $users = [$demo->id, $miembro->id];

        // Ingresos (varios en los últimos 2 meses).
        foreach (range(1, 5) as $i) {
            Income::factory()->create([
                'household_id' => $household->id,
                'user_id' => fake()->randomElement($users),
                'account_id' => $accounts->random()->id,
                'category_id' => $incomeCategories->random(),
                'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            ]);
        }

        // Gastos (varios en los últimos 2 meses).
        foreach (range(1, 22) as $i) {
            Expense::factory()->create([
                'household_id' => $household->id,
                'user_id' => fake()->randomElement($users),
                'account_id' => $accounts->random()->id,
                'category_id' => $expenseCategories->random(),
                'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            ]);
        }

        // Saldos coherentes con los movimientos generados.
        $balanceService = app(AccountBalanceService::class);
        $accounts->each(fn (Account $account) => $balanceService->recompute($account));
    }
}
