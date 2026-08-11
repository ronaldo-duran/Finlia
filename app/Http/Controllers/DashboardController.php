<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard inicial: vacío pero preparado para las estadísticas de
     * las próximas épicas (cuentas, ingresos, gastos, disponible...).
     */
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // La app ya opera en America/Bogota (config/app.php).
        $hoy = Carbon::now()->locale('es');

        return view('dashboard', [
            'user' => $user,
            'fechaActual' => $hoy->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            'fechaCorta' => $hoy->format('d/m/Y'),
        ]);
    }
}
