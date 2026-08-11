<?php

namespace Database\Seeders;

use App\Models\User;
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
        User::factory()->create([
            'name' => 'Usuario Demo Finlia',
            'email' => 'demo@finlia.test',
            'password' => 'finlia123',
        ]);
    }
}
