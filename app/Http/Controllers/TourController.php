<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TourStatus;
use App\Http\Requests\Tour\StoreTourViewRequest;
use App\Http\Requests\Tour\UpdateTourPreferenceRequest;
use App\Services\TourService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Guías de pantalla: progreso y preferencia del usuario (ADR-0045).
 *
 * Fino a propósito (ADR-0010): valida, autoriza y delega en TourService. No
 * decide nada sobre el contenido de las guías — eso vive en config/tours.php.
 *
 * Nada aquí acepta un id de usuario: todo aplica al autenticado, así que no
 * hay forma de marcarle una guía como vista a otra persona ni de apagarle las
 * suyas.
 */
class TourController extends Controller
{
    public function __construct(private readonly TourService $tours) {}

    /**
     * «Ya la vi»: la guía deja de aparecer sola.
     *
     * La llama el navegador al terminar o al saltar. Responde 204 porque no
     * hay nada que repintar: la página ya está donde tiene que estar.
     */
    public function store(StoreTourViewRequest $request, string $tour): Response
    {
        abort_if($this->tours->find($tour) === null, 404);

        $this->tours->markSeen(
            $request->user(),
            $tour,
            TourStatus::from($request->validated('status')),
        );

        return response()->noContent();
    }

    /**
     * Enciende o apaga TODAS las guías.
     *
     * Apagarlas no borra el progreso: quien vuelva a encenderlas retoma donde
     * iba en vez de comerse las diez guías otra vez.
     */
    public function preference(UpdateTourPreferenceRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        $user->tours_enabled = $request->boolean('enabled');
        $user->save();

        return $request->expectsJson()
            ? response()->noContent()
            : back()->with('status', $user->tours_enabled
                ? __('Las guías vuelven a estar activas.')
                : __('No volverás a ver las guías.'));
    }

    /**
     * «Volver a verlas desde el principio».
     *
     * Borra el progreso y enciende el interruptor: pedir verlas de nuevo con
     * las guías apagadas no llevaría a ninguna parte, y sería un botón que no
     * hace nada visible.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $this->tours->reset($user);

        $user->tours_enabled = true;
        $user->save();

        return back()->with('status', __('Listo: las guías volverán a aparecer al entrar a cada pantalla.'));
    }
}
