<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resumen rápido de gastos para el panel.
 */
class QuickReportController extends Controller
{
    /**
     * Devuelve el detalle de un gasto del hogar activo.
     */
    public function show(int $id): JsonResponse
    {
        $expense = Expense::where('household_id', active_household_id())->findOrFail($id);

        $this->authorize('view', $expense);

        return response()->json([
            'id' => $expense->id,
            'monto' => $expense->amount,
            'fecha' => $expense->date,
        ]);
    }

    /**
     * Suma lo gastado por el hogar activo en los últimos días.
     */
    public function total(Request $request): JsonResponse
    {
        $dias = (int) $request->input('dias', 30);

        // La suma la hace el motor sobre la columna DECIMAL: no pasa por float.
        $total = Expense::where('household_id', active_household_id())
            ->where('date', '>=', now()->subDays($dias))
            ->sum('amount');

        return response()->json(['total' => $total]);
    }
}
