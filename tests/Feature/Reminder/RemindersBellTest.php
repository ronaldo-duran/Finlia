<?php

declare(strict_types=1);

namespace Tests\Feature\Reminder;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * La campanita del navbar (Épica 9): se alimenta del summary CACHEADO
 * (ReminderService::cachedSummary). Si un despliegue cambia la forma
 * del summary, la caché puede seguir sirviendo la forma anterior hasta
 * que expire su TTL — renderizar con esa forma vieja no debe reventar.
 */
class RemindersBellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Household}
     */
    private function setupHousehold(): array
    {
        $owner = User::factory()->create();
        $household = app(HouseholdService::class)->createHousehold($owner->id, 'Hogar A');

        return [$owner, $household];
    }

    public function test_la_campanita_renderiza_aunque_la_cache_no_traiga_preview(): void
    {
        [$owner, $household] = $this->setupHousehold();

        // Simula una caché con esquema anterior al fix (sin `preview`).
        Cache::put(
            ReminderService::summaryCacheKey($household->id),
            ['overdue' => 1, 'upcoming' => 1, 'attention' => 2, 'total' => 5],
            now()->addMinutes(10),
        );

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Recordatorios')
            ->assertSee('y 2 más', escape: false);
    }
}
