<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReminderStatus;
use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderEmailRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderSettingsRequest;
use App\Models\Household;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Recordatorios del hogar (Épica 9): lista unificada de obligaciones
 * (recurrentes, deudas, metas y avisos sueltos), alta/edición/borrado de
 * los sueltos y el interruptor del hogar. La lógica vive en
 * ReminderService (ADR-0010); este controlador solo valida, autoriza y
 * despacha.
 */
class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function index(Request $request): View|RedirectResponse
    {
        $household = active_household();

        // Defensivo: un usuario autenticado siempre tiene hogar (ADR-0011).
        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('viewAny', Reminder::class);

        $enabled = (bool) $household->reminders_enabled;

        return view('reminders.index', [
            // list() fresco siempre: esta página es la fuente del estado.
            // El summary (badge/campanita) puede ir cacheado porque sus
            // mutaciones invalidan la clave (ReminderSummaryCacheObserver).
            'items' => $enabled ? $this->reminders->list($household->id) : collect(),
            'summary' => $enabled
                ? $this->reminders->cachedSummary($household->id)
                : ['overdue' => 0, 'upcoming' => 0, 'attention' => 0, 'total' => 0],
            // Historial reciente de avisos sueltos atendidos.
            'completed' => $household->reminders()
                ->where('status', ReminderStatus::Completed->value)
                ->latest('updated_at')
                ->take(10)
                ->get(),
            'enabled' => $enabled,
            // El interruptor del hogar solo lo mueve el administrador.
            'canManageSettings' => $request->user()->can('update', $household),
            // Preferencia personal de digest (ADR-0028): opt-in por miembro.
            'emailEnabled' => (bool) $household->members
                ->firstWhere('id', $request->user()->id)
                ?->pivot->reminders_email,
            'userEmail' => $request->user()->email,
        ]);
    }

    public function store(StoreReminderRequest $request): RedirectResponse
    {
        $this->authorize('create', Reminder::class);

        active_household()->reminders()->create($request->validatedData());

        return redirect()
            ->route('reminders.index')
            ->with('status', __('Recordatorio añadido.'));
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('update', $reminder);

        $reminder->update($request->validatedData());

        return redirect()
            ->route('reminders.index')
            ->with('status', __('Recordatorio actualizado.'));
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('delete', $reminder);

        $reminder->delete();

        return redirect()
            ->route('reminders.index')
            ->with('status', __('Recordatorio ":title" eliminado.', ['title' => $reminder->title]));
    }

    /**
     * Atiende un recordatorio suelto: avanza la fecha si se repite o lo
     * cierra si era de una sola vez. No genera gasto (ADR-0027).
     */
    public function complete(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('complete', $reminder);

        $finished = $this->reminders->complete($reminder);

        $status = $finished
            ? __('Recordatorio ":title" completado.', ['title' => $reminder->title])
            : __('":title" atendido: próxima fecha :date.', [
                'title' => $reminder->title,
                'date' => $reminder->due_date->format('d/m/Y'),
            ]);

        return redirect()->route('reminders.index')->with('status', $status);
    }

    /**
     * Activa o desactiva los recordatorios del hogar (solo el administrador).
     */
    public function settings(UpdateReminderSettingsRequest $request): RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('update', $household);

        $data = $request->validatedData();
        $household->update($data);

        $status = $data['reminders_enabled']
            ? __('Recordatorios activados.')
            : __('Recordatorios desactivados.');

        return redirect()->route('reminders.index')->with('status', $status);
    }

    /**
     * Preferencia personal de digest por correo (ADR-0028): opt-in por
     * miembro del hogar activo, nunca global ni de hogar.
     */
    public function email(UpdateReminderEmailRequest $request): RedirectResponse
    {
        $household = active_household();

        if ($household === null) {
            return redirect()->route('households.create');
        }

        $this->authorize('manageEmailPreferences', Reminder::class);

        $enabled = $request->validatedData()['reminders_email'];

        $household->members()->updateExistingPivot(
            $request->user()->id,
            ['reminders_email' => $enabled],
        );

        $status = $enabled
            ? __('Resumen por correo activado: máximo un correo al día si tienes urgentes.')
            : __('Resumen por correo desactivado.');

        return redirect()->route('reminders.index')->with('status', $status);
    }

    /**
     * Baja del digest desde el propio correo (ADR-0028): URL firmada por
     * usuario+hogar, válida con o sin sesión (el click llega desde el
     * buzón). GET muestra la confirmación; POST es el one-click de
     * RFC 8058 (Gmail/Yahoo) y solo necesita un 204. La firma es la
     * autorización: los parámetros no se pueden manipular.
     */
    public function unsubscribe(Request $request): View|Response
    {
        $user = User::find($request->integer('user'));
        $household = Household::find($request->integer('household'));

        // Firma válida de entidades que ya no existen (cuenta borrada):
        // no hay suscripción que apagar, pero tampoco hay nada que ocultar.
        if ($user === null || $household === null) {
            abort(404);
        }

        // Idempotente y silencioso: si ya estaba en false, o la membresía
        // ya no existe (UPDATE de 0 filas), el resultado es el mismo.
        $household->members()->updateExistingPivot($user->id, [
            'reminders_email' => false,
        ]);

        Log::info('Baja del digest de recordatorios', [
            'household_id' => $household->id,
            'user_id' => $user->id,
        ]);

        if ($request->isMethod('POST')) {
            return response()->noContent();
        }

        return view('reminders.unsubscribe', [
            'householdName' => $household->name,
            'appName' => config('app.name'),
        ]);
    }
}
