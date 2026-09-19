<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompulsiveKind;
use App\Enums\CompulsiveMood;
use App\Enums\CompulsivePlanned;
use App\Enums\CompulsiveTrigger;
use App\Http\Requests\Expense\StoreCompulsiveSurveyRequest;
use App\Http\Requests\Expense\StorePurchaseFollowUpRequest;
use App\Models\CompulsiveSurveyResponse;
use App\Models\Expense;
use App\Services\CompulsiveSurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Endpoint del árbol de decisión de compras (Épica 12).
 * El modal del árbol vive en el layout y se abre con
 * `session('compulsive_survey_expense_id')`. El seguimiento se lista
 * en `/compras/revisar` y guarda la respuesta al mes de la compra.
 */
class CompulsiveSurveyController extends Controller
{
    public function __construct(private readonly CompulsiveSurveyService $service) {}

    public function store(StoreCompulsiveSurveyRequest $request, Expense $expense): RedirectResponse
    {
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

    /**
     * Compras del hogar activo cuyo seguimiento ya venció y no se ha contestado.
     * Al usuario se le muestran las compras que él mismo respondió — así el
     * seguimiento vuelve a la misma persona que decidió, no a otro miembro.
     */
    public function followUpIndex(Request $request): View|RedirectResponse
    {
        $household = active_household();
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $pending = CompulsiveSurveyResponse::query()
            ->where('household_id', $household->id)
            ->where('user_id', $request->user()->id)
            ->pendingFollowUp()
            ->with('expense.category')
            ->orderBy('follow_up_due_at')
            ->get();

        return view('purchases.review', [
            'pending' => $pending,
        ]);
    }

    public function followUpStore(
        StorePurchaseFollowUpRequest $request,
        CompulsiveSurveyResponse $response,
    ): RedirectResponse {
        // Aislamiento: solo el autor original de la respuesta la puede cerrar,
        // y ambos deben estar en el hogar activo.
        abort_unless(
            $response->household_id === active_household_id()
                && $response->user_id === $request->user()->id,
            403,
        );

        $regret = $request->validated('regret');

        $this->service->answerFollowUp(
            $response,
            CompulsiveMood::from((int) $request->validated('mood_after')),
            $regret === null ? null : (bool) $regret,
            $request->validated('note'),
        );

        return redirect()
            ->route('purchases.review.index')
            ->with('status', __('¡Listo! Anotamos cómo te sientes con esa compra.'));
    }
}
