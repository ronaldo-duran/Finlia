<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SavingsGoalStatus;
use App\Http\Requests\SavingsGoal\StoreSavingsGoalContributionRequest;
use App\Http\Requests\SavingsGoal\StoreSavingsGoalRequest;
use App\Http\Requests\SavingsGoal\UpdateSavingsGoalRequest;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Services\SavingsGoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Metas de ahorro (Épica 7). Controlador fino: progreso, recomendaciones y
 * recálculos viven en SavingsGoalService (ADR-0010).
 */
class SavingsGoalController extends Controller
{
    public function __construct(private readonly SavingsGoalService $goals) {}

    /**
     * Panel de metas: resumen y listado según el filtro de estado
     * (?estado= vigentes|logradas|archivadas).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', SavingsGoal::class);

        // `?estado[]=x` haría que query() devuelva un array: se comprueba el
        // tipo antes de mirar el valor (mismo fallo ya corregido en deudas).
        $requested = $request->query('estado');
        $estado = is_string($requested) ? $requested : '';
        $estado = in_array($estado, ['vigentes', 'logradas', 'archivadas'], true) ? $estado : 'vigentes';

        $query = match ($estado) {
            'logradas' => $household->savingsGoals()->where('status', SavingsGoalStatus::Completed->value),
            'archivadas' => $household->savingsGoals()->where('status', SavingsGoalStatus::Archived->value),
            default => $household->savingsGoals()->outstanding(),
        };

        $goals = $query->orderBy('target_date')->orderBy('name')->get();

        return view('savings.index', [
            'summary' => $this->goals->summary($household->id),
            'goals' => $goals,
            'estado' => $estado,
        ]);
    }

    /** Formulario de alta. Puntual: pantalla propia, como el alta de deudas. */
    public function create(): View|RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('create', SavingsGoal::class);

        return view('savings.create');
    }

    /**
     * Detalle: progreso, recomendación de aporte y historial de movimientos.
     */
    public function show(SavingsGoal $savingsGoal): View
    {
        $this->authorize('view', $savingsGoal);

        return view('savings.show', [
            'goal' => $savingsGoal,
            'contributions' => $savingsGoal->contributions()
                ->orderByDesc('date')->orderByDesc('id')->get(),
            'recommendation' => $this->goals->recommendedMonthlyContribution($savingsGoal),
        ]);
    }

    public function store(StoreSavingsGoalRequest $request): RedirectResponse
    {
        $this->authorize('create', SavingsGoal::class);

        $goal = $this->goals->createGoal(active_household(), $request->validatedData());

        return redirect()
            ->route('savings-goals.show', $goal)
            ->with('status', __('Meta ":name" creada.', ['name' => $goal->name]));
    }

    public function edit(SavingsGoal $savingsGoal): View
    {
        $this->authorize('update', $savingsGoal);

        return view('savings.edit', ['goal' => $savingsGoal]);
    }

    public function update(UpdateSavingsGoalRequest $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->goals->updateGoal($savingsGoal, $request->validatedData());

        return redirect()
            ->route('savings-goals.show', $savingsGoal)
            ->with('status', __('Meta actualizada.'));
    }

    public function destroy(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('delete', $savingsGoal);

        // Borrado físico: el historial de aportes es de la meta, y la meta
        // borrada se va con él (cascade). No hay "historia financiera" que
        // preservar: el dinero sigue en las cuentas (ADR-0025).
        $savingsGoal->delete();

        return redirect()
            ->route('savings-goals.index')
            ->with('status', __('Meta ":name" eliminada.', ['name' => $savingsGoal->name]));
    }

    /**
     * Registra un aporte o retiro (ADR-0025: no mueve cuentas; es progreso
     * de la meta).
     */
    public function contribute(StoreSavingsGoalContributionRequest $request, SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('contribute', $savingsGoal);

        $this->goals->registerContribution($savingsGoal, $request->validatedData());

        return redirect()
            ->route('savings-goals.show', $savingsGoal)
            ->with('status', __('Movimiento registrado en ":name".', ['name' => $savingsGoal->name]));
    }

    /** Borra un movimiento y deshace su efecto sobre lo ahorrado. */
    public function destroyContribution(SavingsGoal $savingsGoal, SavingsGoalContribution $contribution): RedirectResponse
    {
        $this->authorize('contribute', $savingsGoal);

        // La meta del movimiento debe ser la de la URL: si no, la petición
        // no corresponde a esta meta y no se toca.
        if ($contribution->savings_goal_id !== $savingsGoal->id) {
            abort(404);
        }

        $this->goals->deleteContribution($contribution);

        return redirect()
            ->route('savings-goals.show', $savingsGoal)
            ->with('status', __('Movimiento eliminado.'));
    }

    /** Pausa: deja de comprometer el aporte mensual en el presupuesto. */
    public function pause(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->goals->pause($savingsGoal);

        return redirect()->back()
            ->with('status', __('Meta ":name" pausada.', ['name' => $savingsGoal->name]));
    }

    /** Reactiva una meta pausada. */
    public function resume(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->goals->resume($savingsGoal);

        return redirect()->back()
            ->with('status', __('Meta ":name" reactivada.', ['name' => $savingsGoal->name]));
    }

    /** Marca la meta como lograda (acción manual del usuario). */
    public function complete(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->goals->complete($savingsGoal);

        return redirect()->back()
            ->with('status', __('¡Bien hecho! ":name" quedó marcada como lograda.', ['name' => $savingsGoal->name]));
    }

    /** Archiva la meta: sale del panel vigente sin perder historial. */
    public function archive(SavingsGoal $savingsGoal): RedirectResponse
    {
        $this->authorize('update', $savingsGoal);

        $this->goals->archive($savingsGoal);

        return redirect()->back()
            ->with('status', __('Meta ":name" archivada.', ['name' => $savingsGoal->name]));
    }
}
