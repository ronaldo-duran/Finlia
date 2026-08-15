<?php

namespace Tests\Feature\Category;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function setupHousehold(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');

        return [$owner, $household];
    }

    public function test_usuario_lista_categorias_globales_y_personales(): void
    {
        [$owner, $household] = $this->setupHousehold();
        Category::create(['name' => 'Alimentación', 'type' => CategoryType::Expense->value, 'household_id' => null, 'is_default' => true]);
        Category::create(['name' => 'Mascotas', 'type' => CategoryType::Expense->value, 'household_id' => $household->id]);

        $this->actingAs($owner)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Alimentación')
            ->assertSee('Mascotas');
    }

    public function test_usuario_puede_crear_una_categoria_personal(): void
    {
        [$owner] = $this->setupHousehold();

        $this->actingAs($owner)->post(route('categories.store'), [
            'name' => 'Gym',
            'type' => 'expense',
            'color' => '#ff0000',
        ])->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Gym', 'type' => 'expense', 'is_default' => false]);
    }

    public function test_usuario_puede_actualizar_su_categoria_personal(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $category = Category::create(['name' => 'Gym', 'type' => 'expense', 'household_id' => $household->id]);

        $this->actingAs($owner)->put(route('categories.update', $category), [
            'name' => 'Deporte',
            'color' => '#00ff00',
        ])->assertRedirect(route('categories.index'));

        $this->assertSame('Deporte', $category->fresh()->name);
    }

    public function test_no_se_puede_eliminar_una_categoria_global(): void
    {
        [$owner] = $this->setupHousehold();
        $global = Category::create(['name' => 'Salud', 'type' => 'expense', 'household_id' => null, 'is_default' => true]);

        $this->actingAs($owner)
            ->delete(route('categories.destroy', $global))
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $global->id]);
    }

    public function test_usuario_puede_eliminar_su_categoria_personal(): void
    {
        [$owner, $household] = $this->setupHousehold();
        $category = Category::create(['name' => 'Gym', 'type' => 'expense', 'household_id' => $household->id]);

        $this->actingAs($owner)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // ===== Aislamiento multi-hogar =====

    public function test_usuario_ajeno_no_puede_borrar_categoria_de_otro_hogar(): void
    {
        [, $household] = $this->setupHousehold();
        $category = Category::create(['name' => 'Gym', 'type' => 'expense', 'household_id' => $household->id]);

        $intruder = User::factory()->create();
        app(HouseholdService::class)->createHousehold($intruder->id, 'Hogar Intruso');

        $this->actingAs($intruder)
            ->delete(route('categories.destroy', $category))
            ->assertForbidden();
    }
}
