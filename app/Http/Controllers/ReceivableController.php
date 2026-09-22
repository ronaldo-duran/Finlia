<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\ReceivableStatus;
use App\Http\Requests\Receivable\StoreReceivableRequest;
use App\Http\Requests\Receivable\UpdateReceivableRequest;
use App\Models\Category;
use App\Models\Receivable;
use App\Services\ReceivableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Cuentas por cobrar (Épica 15). Controlador fino: saldos y resumen
 * viven en ReceivableService (ADR-0010).
 */
class ReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $receivables) {}

    /**
     * Panel de cuentas por cobrar: total pendiente, listado y saldadas.
     */
    public function index(): View|RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', Receivable::class);

        return view('receivables.index', [
            'summary' => $this->receivables->summary($household->id),
            'receivables' => $this->receivables->outstandingForHousehold($household->id),
            'settled' => $household->receivables()
                ->whereNotIn('status', ReceivableStatus::outstandingValues())
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    /**
     * Formulario de alta. Registrar una cuenta por cobrar es puntual, así
     * que vive en su propia pantalla.
     */
    public function create(): View|RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('create', Receivable::class);

        return view('receivables.create', [
            'members' => $household->members()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreReceivableRequest $request): RedirectResponse
    {
        $this->authorize('create', Receivable::class);

        $receivable = $this->receivables->createReceivable(active_household(), $request->validatedData());

        return redirect()
            ->route('receivables.show', $receivable)
            ->with('status', __('Cuenta por cobrar ":name" registrada.', ['name' => $receivable->name]));
    }

    /**
     * Detalle: importe, saldo, historial de cobros y formulario para
     * registrar uno nuevo.
     */
    public function show(Receivable $receivable): View|RedirectResponse
    {
        $this->authorize('view', $receivable);

        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $categories = Category::forHousehold($household->id)
            ->where('type', CategoryType::Income->value)
            ->orderBy('name')
            ->get();

        return view('receivables.show', [
            'receivable' => $receivable->load('debtorUser'),
            'payments' => $receivable->payments()
                ->with(['income', 'account'])
                ->orderByDesc('date')->orderByDesc('id')
                ->get(),
            'accounts' => $household->accounts()->orderBy('name')->get(),
            'categories' => $categories,
        ]);
    }

    public function edit(Receivable $receivable): View|RedirectResponse
    {
        $this->authorize('update', $receivable);

        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        return view('receivables.edit', [
            'receivable' => $receivable,
            'members' => $household->members()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateReceivableRequest $request, Receivable $receivable): RedirectResponse
    {
        $this->authorize('update', $receivable);

        $this->receivables->updateReceivable($receivable, $request->validatedData());

        return redirect()
            ->route('receivables.show', $receivable)
            ->with('status', __('Cuenta por cobrar actualizada.'));
    }

    public function destroy(Receivable $receivable): RedirectResponse
    {
        $this->authorize('delete', $receivable);

        $receivable->delete();

        return redirect()
            ->route('receivables.index')
            ->with('status', __('Cuenta por cobrar ":name" eliminada.', ['name' => $receivable->name]));
    }
}
