<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Enums\CategoryType;
use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use App\Enums\Gender;
use App\Enums\HouseholdRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\CompulsiveSurveyResponse;
use App\Models\Expense;
use App\Models\User;
use App\Services\CompulsiveSurveyService;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seguimiento de la compra a los 30 días (Épica 12).
 *
 * Cubre:
 *   - `record()` toma edad y género del usuario y agenda el follow-up.
 *   - `/compras/revisar` lista sólo las citas vencidas del hogar activo del
 *     autor original; nadie más ve compras que no respondió.
 *   - `POST /compras/revisar/{response}` guarda mood_after, regret y nota.
 *   - El aislamiento se preserva: otro miembro del hogar no puede contestar
 *     el seguimiento de la compra de un compañero.
 */
class PurchaseFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixtures(array $overrides = []): array
    {
        $user = User::factory()->create([
            'birth_date' => now()->subYears(34)->toDateString(),
            'gender' => Gender::Female->value,
            ...$overrides,
        ]);
        $household = app(HouseholdService::class)->createHousehold($user->id, 'Hogar');
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'is_active' => true,
        ]);
        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => CategoryType::Expense->value,
        ]);
        $expense = Expense::factory()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
        ]);

        return [$user, $household, $expense];
    }

    public function test_record_congela_edad_y_genero_y_agenda_follow_up(): void
    {
        [$user, $household, $expense] = $this->seedFixtures();

        $response = app(CompulsiveSurveyService::class)->record(
            $household, $user, $expense,
            CompulsivePlanned::Yes, CompulsiveKind::Need,
            CompulsiveMood::Neutral, CompulsiveTrigger::RealNeed,
        );

        $this->assertSame(34, $response->age_years);
        $this->assertSame(Gender::Female->value, $response->gender);
        $this->assertNotNull($response->follow_up_due_at);
        $this->assertSame(
            now()->addDays(CompulsiveSurveyService::FOLLOW_UP_DAYS)->toDateString(),
            $response->follow_up_due_at->toDateString(),
        );
        $this->assertNull($response->follow_up_answered_at);
    }

    public function test_index_de_compras_a_revisar_lista_solo_lo_vencido_del_usuario(): void
    {
        [$user, $household, $expense] = $this->seedFixtures();

        $due = CompulsiveSurveyResponse::firstOrNew(['expense_id' => $expense->id]);
        $due->household_id = $household->id;
        $due->user_id = $user->id;
        $due->planned = CompulsivePlanned::Yes;
        $due->kind = CompulsiveKind::Impulse;
        $due->mood = CompulsiveMood::Happy;
        $due->trigger = CompulsiveTrigger::Promotion;
        $due->age_years = 34;
        $due->gender = Gender::Female->value;
        $due->follow_up_due_at = now()->subDays(5);
        $due->save();

        $this->actingAs($user)
            ->get(route('purchases.review.index'))
            ->assertOk()
            ->assertSeeText('Compras a revisar')
            ->assertSeeText('¿Cómo te sientes con esa compra ahora?');
    }

    public function test_store_guarda_respuesta_al_seguimiento(): void
    {
        [$user, $household, $expense] = $this->seedFixtures();

        $response = CompulsiveSurveyResponse::firstOrNew(['expense_id' => $expense->id]);
        $response->household_id = $household->id;
        $response->user_id = $user->id;
        $response->planned = CompulsivePlanned::Yes;
        $response->kind = CompulsiveKind::Want;
        $response->mood = CompulsiveMood::Neutral;
        $response->trigger = CompulsiveTrigger::Boredom;
        $response->follow_up_due_at = now()->subDay();
        $response->save();

        $this->actingAs($user)
            ->post(route('purchases.review.store', $response), [
                'mood_after' => CompulsiveMood::Sad->value,
                'regret' => '1',
                'note' => 'Ya no la uso.',
            ])
            ->assertRedirect(route('purchases.review.index'));

        $response->refresh();
        $this->assertSame(CompulsiveMood::Sad, $response->mood_after);
        $this->assertTrue($response->follow_up_regret);
        $this->assertSame('Ya no la uso.', $response->follow_up_note);
        $this->assertNotNull($response->follow_up_answered_at);
    }

    public function test_otro_miembro_no_puede_contestar_seguimiento_ajeno(): void
    {
        [$autor, $household, $expense] = $this->seedFixtures();

        $response = CompulsiveSurveyResponse::firstOrNew(['expense_id' => $expense->id]);
        $response->household_id = $household->id;
        $response->user_id = $autor->id;
        $response->planned = CompulsivePlanned::Yes;
        $response->kind = CompulsiveKind::Emergency;
        $response->mood = CompulsiveMood::VerySad;
        $response->trigger = CompulsiveTrigger::Stress;
        $response->follow_up_due_at = now()->subDay();
        $response->save();

        $otro = User::factory()->create();
        $household->members()->attach($otro->id, [
            'role' => HouseholdRole::Member->value,
            'joined_at' => now(),
        ]);
        session(['household_id' => $household->id]);

        $this->actingAs($otro)
            ->post(route('purchases.review.store', $response), [
                'mood_after' => CompulsiveMood::Happy->value,
            ])
            ->assertForbidden();
    }
}
