<?php

namespace Database\Seeders;

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;
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
        // Usuario de demostración para desarrollo local.
        // El cast 'hashed' del modelo cifra la contraseña al asignarla.
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
    }
}
