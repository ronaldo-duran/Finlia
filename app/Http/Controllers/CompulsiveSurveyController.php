<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use App\Http\Requests\Expense\StoreCompulsiveSurveyRequest;
use App\Models\Expense;
use App\Services\CompulsiveSurveyService;
use Illuminate\Http\RedirectResponse;

/**
 * Endpoint del árbol de decisión de compras (Épica 12, v0.38).
 * El modal vive en el layout y se abre con `session('compulsive_survey_expense_id')`.
 */
class CompulsiveSurveyController extends Controller
{
    public function __construct(private readonly CompulsiveSurveyService $service) {}

    public function store(StoreCompulsiveSurveyRequest $request, Expense $expense): RedirectResponse
    {
        // Reusa la Policy de Expense: solo miembros del hogar pueden guardar.
        $this->authorize('update', $expense);

        $this->service->record(
            $expense->household,
            $request->user(),
            $expense,
            CompulsivePlanned::from((string) $request->validated('planned')),
            CompulsiveKind::from((string) $request->validated('kind')),
            CompulsiveMood::from((int) $request->validated('mood')),
            CompulsiveTrigger::from((string) $request->validated('trigger')),
        );

        return back()->with('status', __('¡Gracias! Anotamos tu respuesta.'));
    }
}
