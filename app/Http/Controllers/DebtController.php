<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\DebtStatus;
use App\Enums\DebtStrategy;
use App\Http\Requests\Debt\StoreDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Models\Category;
use App\Models\Debt;
use App\Services\DebtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Deudas y tarjetas de crédito (Épica 6). Controlador fino: saldos,
 * proyecciones y estrategias viven en DebtService (ADR-0010).
 */
class DebtController extends Controller
{
    public function __construct(private readonly DebtService $debts) {}

    /**
     * Panel de deuda: total, compromiso mensual, progreso y listado.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', Debt::class);

        // `?estrategia[]=x` haría que query() devuelva un array: castearlo a
        // string emite un warning que Laravel convierte en 500. Se comprueba
        // el tipo antes de mirar el valor.
        $requested = $request->query('estrategia');
        $strategy = (is_string($requested) ? DebtStrategy::tryFrom($requested) : null)
            ?? DebtStrategy::Avalanche;

        $ordered = $this->debts->orderByStrategy($household->id, $strategy);

        return view('debts.index', [
            'summary' => $this->debts->summary($household->id),
            'debts' => $ordered->load('account'),
            // Proyección por deuda, indexada por id: la vista no calcula nada.
            'projections' => $ordered->mapWithKeys(
                fn (Debt $debt) => [$debt->id => $this->debts->projectPayoff($debt)]
            ),
            'settled' => $household->debts()
                ->whereNotIn('status', DebtStatus::outstandingValues())
                ->orderByDesc('updated_at')
                ->get(),
            'strategy' => $strategy,
            'strategies' => DebtStrategy::cases(),
            'accounts' => $household->accounts()->orderBy('name')->get(),
        ]);
    }

    /**
     * Detalle: condiciones, progreso, historial de pagos y refinanciaciones.
     */
    public function show(Debt $debt): View
    {
        $this->authorize('view', $debt);

        $household = active_household();

        return view('debts.show', [
            'debt' => $debt->load('account'),
            'payments' => $debt->payments()->with('expense')->orderByDesc('date')->orderByDesc('id')->get(),
            'refinancings' => $debt->refinancings()->orderByDesc('start_date')->get(),
            'projection' => $this->debts->projectPayoff($debt),
            'accounts' => $household->accounts()->orderBy('name')->get(),
            'categories' => Category::forHousehold($household->id)
                ->where('type', CategoryType::Expense->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $this->authorize('create', Debt::class);

        $debt = $this->debts->createDebt(active_household(), $request->validatedData());

        return redirect()
            ->route('debts.show', $debt)
            ->with('status', __('Deuda ":name" registrada.', ['name' => $debt->name]));
    }

    public function update(UpdateDebtRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('update', $debt);

        // El Service recalcula lo derivado: fin previsto (ADR-0022) y saldo,
        // porque cambiar el monto original mueve su línea base (ADR-0020).
        $this->debts->updateDebt($debt, $request->validatedData());

        return redirect()
            ->route('debts.show', $debt)
            ->with('status', __('Deuda actualizada.'));
    }

    public function destroy(Debt $debt): RedirectResponse
    {
        $this->authorize('delete', $debt);

        // Borrado lógico: el historial financiero no se pierde.
        $debt->delete();

        return redirect()
            ->route('debts.index')
            ->with('status', __('Deuda ":name" eliminada.', ['name' => $debt->name]));
    }
}
