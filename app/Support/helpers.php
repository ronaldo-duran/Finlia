<?php

use App\Models\Household;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

if (! function_exists('money')) {
    /**
     * Formatea un monto como moneda COP: "$ 1.000.000,00"
     * (punto miles, coma decimales). Centralizado para que sirva igual
     * en Blade (@money) que en JSON/Services (ADR-0010, ADR-0006).
     */
    function money(int|float|string|null $amount, string $currency = 'COP'): string
    {
        if ($amount === null || $amount === '') {
            $amount = 0;
        }

        return '$ ' . number_format((float) $amount, 2, ',', '.');
    }
}

if (! function_exists('active_household')) {
    /**
     * Resuelve el hogar activo del usuario autenticado.
     *
     * Es capa HTTP (lee sesión), NO un Service de dominio: la noción de
     * "hogar activo" es estado de sesión, no lógica de negocio.
     * El resultado se cachea por petición en el contenedor.
     */
    function active_household(): ?Household
    {
        if (app()->bound('finlia.active_household')) {
            return app('finlia.active_household');
        }

        $user = Auth::user();
        $household = null;

        if ($user) {
            // Si hay uno guardado en sesión y sigue siendo válido, se usa.
            $sessionHouseholdId = Session::get('household_id');

            if ($sessionHouseholdId) {
                $household = $user->households()->find($sessionHouseholdId);
            }

            // Si no, se toma el primero del usuario y se persiste en sesión.
            if (! $household) {
                $household = $user->households()->first();

                if ($household) {
                    Session::put('household_id', $household->id);
                }
            }
        }

        app()->instance('finlia.active_household', $household);

        return $household;
    }
}

if (! function_exists('active_household_id')) {
    function active_household_id(): ?int
    {
        return active_household()?->id;
    }
}
