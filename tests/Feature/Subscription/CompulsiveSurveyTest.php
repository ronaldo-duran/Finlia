<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Enums\BudgetPeriod;
use App\Enums\CategoryType;
use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CompulsiveSurveyResponse;
use App\Models\Expense;
use App\Models\Household;
use App\Models\User;
use App\Services\CompulsiveSurveyService;
use App\Services\HouseholdService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Árbol de decisión de compras (Épica 12): disparo por gasto imprevisto,
 * tope Free y persistencia.
 */
class CompulsiveSurveyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('finlia.subscription.premium_for_all', false);
    }

    private function makeSetup(): array
    {
        $user = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Hogar');

        return [$user, $household];
    }

    private function makeExpense(Household $household, User $user): Expense
    {
        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => CategoryType::Expense->value,
        ]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'is_active' => true,
        ]);

        return Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_should_offer_true_para_gasto_imprevisto(): void
    {
        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        $this->assertTrue(app(CompulsiveSurveyService::class)->shouldOffer($household, $expense));
    }

    public function test_should_offer_false_cuando_hay_presupuesto_para_la_categoria_del_mes(): void
    {
        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        (new Budget)->forceFill([
            'household_id' => $household->id,
            'category_id' => $expense->category_id,
            'amount' => 500000,
            'period' => BudgetPeriod::Monthly->value,
            'year' => $expense->date->year,
            'month' => $expense->date->month,
        ])->save();

        $this->assertFalse(app(CompulsiveSurveyService::class)->shouldOffer($household, $expense));
    }

    public function test_should_offer_true_cuando_el_presupuesto_es_de_otra_categoria(): void
    {
        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        $otra = Category::factory()->create([
            'household_id' => $household->id,
            'type' => CategoryType::Expense->value,
        ]);
        (new Budget)->forceFill([
            'household_id' => $household->id,
            'category_id' => $otra->id,
            'amount' => 500000,
            'period' => BudgetPeriod::Monthly->value,
            'year' => $expense->date->year,
            'month' => $expense->date->month,
        ])->save();

        $this->assertTrue(app(CompulsiveSurveyService::class)->shouldOffer($household, $expense));
    }

    public function test_kill_switch_apaga_el_disparo(): void
    {
        Config::set('finlia.compulsive_survey.enabled', false);

        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        $this->assertFalse(app(CompulsiveSurveyService::class)->shouldOffer($household, $expense));
    }

    public function test_no_muestra_modal_dos_veces_el_mismo_dia(): void
    {
        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        $service = app(CompulsiveSurveyService::class);
        $service->markShownToday($user);

        $this->assertFalse($service->shouldOffer($household, $expense, $user->fresh()));
    }

    public function test_muestra_modal_de_nuevo_al_dia_siguiente(): void
    {
        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        $user->compulsive_survey_last_shown_at = now()->subDay();
        $user->save();

        $service = app(CompulsiveSurveyService::class);
        $this->assertTrue($service->shouldOffer($household, $expense, $user->fresh()));
    }

    public function test_al_ofrecer_el_modal_se_marca_el_dia(): void
    {
        [$user, $household] = $this->makeSetup();
        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => CategoryType::Expense->value,
        ]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => 15000,
            'date' => now()->toDateString(),
            'category_id' => $category->id,
            'account_id' => $account->id,
            'description' => 'Test',
            'payment_method' => 'cash',
        ])->assertSessionHas('compulsive_survey_expense_id');

        $this->assertNotNull($user->fresh()->compulsive_survey_last_shown_at);
    }

    public function test_free_agotado_no_ofrece_mas_encuestas(): void
    {
        [$user, $household] = $this->makeSetup();
        $service = app(CompulsiveSurveyService::class);

        for ($i = 0; $i < 5; $i++) {
            $expense = $this->makeExpense($household, $user);
            $service->record(
                $household,
                $user,
                $expense,
                CompulsivePlanned::Yes,
                CompulsiveKind::Need,
                CompulsiveMood::Neutral,
                CompulsiveTrigger::RealNeed,
            );
        }

        $sixth = $this->makeExpense($household, $user);
        $this->assertFalse($service->shouldOffer($household, $sixth));
    }

    public function test_premium_no_tiene_tope(): void
    {
        [$user, $household] = $this->makeSetup();
        app(SubscriptionService::class)->grantPremium($household, now()->addMonth(), 'Prueba');

        $service = app(CompulsiveSurveyService::class);

        for ($i = 0; $i < 5; $i++) {
            $expense = $this->makeExpense($household, $user);
            $service->record($household, $user, $expense,
                CompulsivePlanned::Yes, CompulsiveKind::Need,
                CompulsiveMood::Neutral, CompulsiveTrigger::RealNeed);
        }

        $sixth = $this->makeExpense($household, $user);
        $this->assertTrue($service->shouldOffer($household->fresh(), $sixth));
    }

    public function test_registrar_gasto_dispara_flash_de_encuesta(): void
    {
        [$user, $household] = $this->makeSetup();
        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => CategoryType::Expense->value,
        ]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => 15000,
            'date' => now()->toDateString(),
            'category_id' => $category->id,
            'account_id' => $account->id,
            'description' => 'Test',
            'payment_method' => 'cash',
        ])->assertSessionHas('compulsive_survey_expense_id');
    }

    public function test_endpoint_guarda_respuesta_al_arbol(): void
    {
        [$user, $household] = $this->makeSetup();
        $expense = $this->makeExpense($household, $user);

        $this->actingAs($user)->post(
            route('expenses.survey.store', ['expense' => $expense]),
            [
                'planned' => CompulsivePlanned::No->value,
                'kind' => CompulsiveKind::Impulse->value,
                'mood' => CompulsiveMood::Happy->value,
                'trigger' => CompulsiveTrigger::Promotion->value,
            ],
        )->assertRedirect();

        $this->assertDatabaseHas('compulsive_survey_responses', [
            'expense_id' => $expense->id,
            'planned' => CompulsivePlanned::No->value,
            'kind' => CompulsiveKind::Impulse->value,
            'mood' => CompulsiveMood::Happy->value,
            'trigger' => CompulsiveTrigger::Promotion->value,
        ]);
    }

    public function test_conteo_es_mensual(): void
    {
        [$user, $household] = $this->makeSetup();
        $service = app(SubscriptionService::class);

        for ($i = 0; $i < 5; $i++) {
            $expense = $this->makeExpense($household, $user);
            (new CompulsiveSurveyResponse)->forceFill([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'expense_id' => $expense->id,
                'planned' => CompulsivePlanned::Yes->value,
                'kind' => CompulsiveKind::Need->value,
                'mood' => 3,
                'trigger' => CompulsiveTrigger::RealNeed->value,
                'created_at' => now()->subMonthsNoOverflow(2),
                'updated_at' => now()->subMonthsNoOverflow(2),
            ])->save();
        }

        $this->assertSame(0, $service->compulsiveSurveysUsedThisMonth($household));
        $this->assertTrue($service->canAskCompulsiveSurvey($household));
    }
}
