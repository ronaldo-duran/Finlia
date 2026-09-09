<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BudgetPeriod;
use App\Enums\ColombianRegion;
use App\Enums\DebtPaymentType;
use App\Enums\DebtType;
use App\Enums\Frequency;
use App\Enums\HouseholdRole;
use App\Enums\SavingsGoalContributionType;
use App\Enums\SavingsGoalPriority;
use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\Income;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DebtService;
use App\Services\HouseholdService;
use App\Services\SavingsGoalService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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

        // Versión inicial de los términos (Plan 03). Se publica aquí para
        // que el flujo de aceptación exista desde el primer arranque.
        $this->call(TermsVersionSeeder::class);

        // Usuario de demostración para desarrollo local.
        $demo = User::factory()->create([
            'name' => 'Usuario Demo Finlia',
            'email' => 'demo@finlia.test',
            'password' => 'finlia123',
            'birth_date' => '1990-05-12',
            'region' => ColombianRegion::BogotaDc->value,
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
            'birth_date' => '1995-09-20',
            'region' => ColombianRegion::Antioquia->value,
        ]);
        $household->members()->attach($miembro->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now()->subDays(3),
        ]);

        // Los usuarios demo ya "aceptaron" la versión inicial de los
        // términos: consentimientos de demostración, para que el login
        // local (y los e2e) no caiga en la pantalla de aceptación.
        $terminos = TermsVersion::current();
        if ($terminos !== null) {
            $demo->acceptTerms($terminos, '127.0.0.1');
            $miembro->acceptTerms($terminos, '127.0.0.1');
        }

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
            ['name' => 'Efectivo', 'type' => AccountType::Cash->value, 'initial_balance' => 450000],
            ['name' => 'Bancolombia', 'type' => AccountType::Bank->value, 'initial_balance' => 5800000],
            ['name' => 'Nequi', 'type' => AccountType::DigitalWallet->value, 'initial_balance' => 620000],
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

        // Ingresos: ~uno por mes en los últimos 6 meses (Épica 8: la
        // comparación de períodos y la evolución mensual necesitan historial).
        foreach (range(1, 7) as $i) {
            Income::factory()->create([
                'household_id' => $household->id,
                'user_id' => fake()->randomElement($users),
                'account_id' => $accounts->random()->id,
                'category_id' => $incomeCategories->random(),
                'date' => fake()->dateTimeBetween('-5 months', 'now')->format('Y-m-d'),
            ]);
        }

        // Gastos repartidos en los últimos 6 meses.
        foreach (range(1, 30) as $i) {
            Expense::factory()->create([
                'household_id' => $household->id,
                'user_id' => fake()->randomElement($users),
                'account_id' => $accounts->random()->id,
                'category_id' => $expenseCategories->random(),
                'date' => fake()->dateTimeBetween('-5 months', 'now')->format('Y-m-d'),
            ]);
        }

        // Los movimientos de arriba son historial: alimentan las series de seis
        // meses de los reportes, y por eso van al azar. El MES EN CURSO no se
        // deja al azar — con 30 gastos repartidos en seis meses, al mes actual
        // le tocan uno o ninguno, y el Panel abre en ceros justo para quien
        // arranca la demo por primera vez.
        $this->seedCurrentMonth($household, $accounts, $users);

        // Saldos coherentes con los movimientos generados.
        $balanceService = app(AccountBalanceService::class);
        $accounts->each(fn (Account $account) => $balanceService->recompute($account));

        $this->seedBudgets($household, $expenseCategories, $incomeCategories);
        $this->seedRecurringExpenses($household, $accounts);
        $this->seedDebts($household, $accounts);
        $this->seedSavingsGoals($household);
        $this->seedReminders($household);
    }

    /**
     * Movimientos FALSOS del mes en curso, con importes fijos.
     *
     * Son los que alimentan el Panel ("gastos del mes", presupuesto consumido)
     * y los que salen en las capturas del README, así que no pueden depender
     * del azar ni del día del mes en que se siembre. Los importes se eligen
     * para consumir ~40 % del presupuesto de seedBudgets(): suficiente para
     * que las barras y los gráficos digan algo, lejos de la alerta del 80 %.
     *
     * @param  array<int, int>  $users
     */
    private function seedCurrentMonth(Household $household, Collection $accounts, array $users): void
    {
        $now = Carbon::now(config('app.timezone'));
        $categoryByName = Category::whereNull('household_id')->pluck('id', 'name');
        $banco = $accounts->firstWhere('name', 'Bancolombia');
        $efectivo = $accounts->firstWhere('name', 'Efectivo');
        $nequi = $accounts->firstWhere('name', 'Nequi');

        // Los dos salarios del hogar, ya recibidos. Coinciden con los
        // ingresos esperados de seedBudgets(): el "puedes gastar" compara
        // ambos, y descuadrarlos haría que la demo se contradiga a sí misma.
        foreach ([[4200000, $users[0]], [3100000, $users[1]]] as [$amount, $userId]) {
            Income::factory()->create([
                'household_id' => $household->id,
                'user_id' => $userId,
                'account_id' => $banco?->id,
                'category_id' => $categoryByName['Salario'] ?? null,
                'amount' => $amount,
                'date' => $now->copy()->startOfMonth()->toDateString(),
                'description' => 'Salario del mes',
            ]);
        }

        // Canasta del mes. El día se recorta a hoy: sembrar un gasto con
        // fecha futura lo dejaría fuera de "gastos del mes" y descuadraría
        // el presupuesto consumido.
        collect([
            ['Mercado quincenal', 'Alimentación', 385000, 2, $banco],
            ['Ropa de los niños', 'Compras', 210000, 3, $banco],
            ['Gasolina', 'Transporte', 120000, 3, $efectivo],
            ['Almuerzos del trabajo', 'Alimentación', 96000, 4, $efectivo],
            ['Recibo de luz', 'Servicios', 148000, 5, $banco],
            ['Comida del perro', 'Mascotas', 89000, 5, $nequi],
            ['Cine en familia', 'Entretenimiento', 74000, 6, $nequi],
            ['Farmacia', 'Salud', 52000, 7, $efectivo],
            ['Transporte público', 'Transporte', 38000, 8, $nequi],
        ])->each(function (array $gasto) use ($household, $categoryByName, $now, $users): void {
            [$descripcion, $categoria, $monto, $dia, $cuenta] = $gasto;

            Expense::factory()->create([
                'household_id' => $household->id,
                'user_id' => fake()->randomElement($users),
                'account_id' => $cuenta?->id,
                'category_id' => $categoryByName[$categoria] ?? null,
                'amount' => $monto,
                'date' => $now->copy()->setDay(min($dia, $now->day))->toDateString(),
                'description' => $descripcion,
            ]);
        });
    }

    /**
     * Presupuestos e ingresos esperados FALSOS del mes en curso (Épica 4),
     * para que el dashboard muestre datos reales de cálculo desde el arranque.
     */
    private function seedBudgets(Household $household, Collection $expenseCategories, Collection $incomeCategories): void
    {
        $now = Carbon::now(config('app.timezone'));

        // household_id no es fillable en estos modelos: se asigna por relación.
        // Ingresos mensuales esperados: base del "puedes gastar".
        // Dos salarios: el hogar demo tiene dos miembros, y los importes
        // coinciden con los ingresos que seedCurrentMonth() ya registró como
        // recibidos. Entre los dos cubren los compromisos con holgura — un
        // hogar insolvente no demuestra nada del "puedes gastar".
        collect([
            ['name' => 'Salario titular', 'amount' => 4200000, 'day_of_month' => 1],
            ['name' => 'Salario del miembro', 'amount' => 3100000, 'day_of_month' => 1],
        ])->each(fn (array $data) => $household->expectedIncomes()->create([
            'category_id' => $incomeCategories->first(),
            ...$data,
        ]));

        // Presupuesto total del mes + tres categorías.
        $household->budgets()->create([
            'category_id' => null,
            'amount' => 3000000,
            'period' => BudgetPeriod::Monthly->value,
            'year' => $now->year,
            'month' => $now->month,
        ]);

        $expenseCategories->take(3)->each(fn (int $categoryId, int $i) => $household->budgets()->create([
            'category_id' => $categoryId,
            'amount' => [800000, 400000, 250000][$i],
            'period' => BudgetPeriod::Monthly->value,
            'year' => $now->year,
            'month' => $now->month,
        ]));
    }

    /**
     * Gastos recurrentes FALSOS (Épica 5): fijos mensuales + obligaciones
     * anuales, para que "Próximas obligaciones" y el dinero disponible
     * tengan datos desde el arranque.
     */
    private function seedRecurringExpenses(Household $household, Collection $accounts): void
    {
        $now = Carbon::now(config('app.timezone'));
        $bank = $accounts->firstWhere('name', 'Bancolombia');
        $categoryByName = Category::whereNull('household_id')
            ->where('type', 'expense')
            ->pluck('id', 'name');

        // Fecha del próximo día 5 (arriendo) y próximo día 20 (suscripción).
        $day5 = $now->copy()->setDay(5);
        if ($day5->isPast()) {
            $day5->addMonthNoOverflow();
        }
        $day20 = $now->copy()->setDay(20);
        if ($day20->isPast()) {
            $day20->addMonthNoOverflow();
        }

        collect([
            // Fijos de alta frecuencia (seam fixed_expenses).
            ['name' => 'Arriendo', 'amount' => 1200000, 'frequency' => Frequency::Monthly, 'next_date' => $day5, 'category' => 'Vivienda'],
            ['name' => 'Internet hogares', 'amount' => 95000, 'frequency' => Frequency::Monthly, 'next_date' => $day20, 'category' => 'Servicios', 'auto_generate' => true],
            // Obligaciones menos frecuentes (seam recurring).
            ['name' => 'SOAT carro', 'amount' => 600000, 'frequency' => Frequency::Yearly, 'next_date' => $now->copy()->addDays(45), 'category' => 'Transporte'],
            ['name' => 'Mantenimiento moto', 'amount' => 280000, 'frequency' => Frequency::Semester, 'next_date' => $now->copy()->addDays(12), 'category' => 'Transporte'],
        ])->each(function (array $data) use ($household, $categoryByName, $bank): void {
            $household->recurringExpenses()->create([
                'category_id' => $categoryByName[$data['category']] ?? null,
                'account_id' => $bank?->id,
                'name' => $data['name'],
                'amount' => $data['amount'],
                'frequency' => $data['frequency']->value,
                'next_date' => $data['next_date']->toDateString(),
                'is_active' => true,
                'auto_generate' => $data['auto_generate'] ?? false,
            ]);
        });
    }

    /**
     * Deudas de demostración (Épica 6). Datos FALSOS: una tarjeta con algo
     * de historial de pagos y un préstamo recién empezado, para que el panel
     * de deuda y las proyecciones no salgan vacíos.
     */
    private function seedDebts(Household $household, Collection $accounts): void
    {
        $debts = app(DebtService::class);
        $now = Carbon::now(config('app.timezone'));
        $card = $accounts->firstWhere('type', AccountType::CreditCard);

        $tarjeta = $debts->createDebt($household, [
            'account_id' => $card?->id,
            'name' => 'Tarjeta de crédito',
            'institution' => 'Banco de demostración',
            'type' => DebtType::CreditCard->value,
            'original_amount' => 4800000,
            'interest_rate' => 28.5,
            'interest_rate_type' => 'fixed',
            // minimum_payment se omite a propósito: lo calcula el Service a
            // partir de monto, tasa y plazo (ADR-0023), igual que el formulario.
            'planned_payment' => 800000,
            'term_months' => 12,
            'due_day' => 15,
            'start_date' => $now->copy()->subMonths(8)->toDateString(),
        ]);

        // Un par de cuotas ya pagadas: el saldo baja solo (ADR-0020).
        foreach ([2, 1] as $monthsAgo) {
            $tarjeta->payments()->forceCreate([
                'household_id' => $household->id,
                'amount' => 800000,
                'date' => $now->copy()->subMonths($monthsAgo)->setDay(15)->toDateString(),
                'type' => DebtPaymentType::Scheduled->value,
            ]);
        }
        $debts->recalculateBalance($tarjeta);

        $debts->createDebt($household, [
            'name' => 'Crédito moto',
            'institution' => 'Financiera de demostración',
            'type' => DebtType::Vehicle->value,
            'original_amount' => 9000000,
            'interest_rate' => 16.0,
            'interest_rate_type' => 'fixed',
            'planned_payment' => 450000,
            'term_months' => 24,
            'due_day' => 5,
            'start_date' => $now->copy()->subMonth()->toDateString(),
        ]);
    }

    /**
     * Metas de ahorro FALSAS (Épica 7): un fondo de emergencia con algo de
     * historial y una meta de viaje, para que el panel y el término
     * `savings` del dinero disponible tengan datos desde el arranque.
     */
    private function seedSavingsGoals(Household $household): void
    {
        $goals = app(SavingsGoalService::class);
        $now = Carbon::now(config('app.timezone'));

        // Fondo de emergencia: 4 aportes de 250.000 en los últimos meses.
        $fondo = $goals->createGoal($household, [
            'name' => 'Fondo de emergencia',
            'target_amount' => 6000000,
            'target_date' => null,
            'priority' => SavingsGoalPriority::High->value,
            'monthly_commitment' => 500000,
            'is_emergency_fund' => true,
        ]);
        foreach ([4, 3, 2, 1] as $monthsAgo) {
            $goals->registerContribution($fondo, [
                'amount' => 250000,
                'date' => $now->copy()->subMonths($monthsAgo)->setDay(10)->toDateString(),
                'type' => SavingsGoalContributionType::Deposit->value,
            ]);
        }

        // Meta de viaje: un solo aporte inicial y un retiro de prueba.
        $viaje = $goals->createGoal($household, [
            'name' => 'Viaje a San Andrés',
            'target_amount' => 3500000,
            'target_date' => $now->copy()->addMonthsNoOverflow(10)->toDateString(),
            'priority' => SavingsGoalPriority::Medium->value,
            'monthly_commitment' => 300000,
        ]);
        $goals->registerContribution($viaje, [
            'amount' => 700000,
            'date' => $now->copy()->subMonths(2)->toDateString(),
            'type' => SavingsGoalContributionType::Deposit->value,
        ]);
        $goals->registerContribution($viaje, [
            'amount' => 200000,
            'date' => $now->copy()->subMonth()->toDateString(),
            'type' => SavingsGoalContributionType::Withdrawal->value,
            'notes' => 'Gasto inesperado',
        ]);
    }

    /**
     * Recordatorios sueltos FALSOS (Épica 9): los derivados (recurrentes,
     * deudas, metas) ya existen por los seeds anteriores; aquí solo hacen
     * falta avisos propios para que la lista muestre los tres estados.
     */
    private function seedReminders(Household $household): void
    {
        $now = Carbon::now(config('app.timezone'));

        $household->reminders()->create([
            'title' => 'Tecnomecánica del carro',
            'amount' => 250000,
            'due_date' => $now->copy()->subDays(4)->toDateString(),
            'frequency' => null,
            'notes' => 'Revisar el certificado vigente antes de la cita.',
        ]);

        $household->reminders()->create([
            'title' => 'Renovar pasaporte',
            'amount' => 180000,
            'due_date' => $now->copy()->addDays(6)->toDateString(),
            'frequency' => null,
        ]);

        $household->reminders()->create([
            'title' => 'Impuesto predial',
            'amount' => 900000,
            'due_date' => $now->copy()->addMonths(3)->setDay(1)->toDateString(),
            'frequency' => Frequency::Yearly->value,
        ]);
    }
}
