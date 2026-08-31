<?php

namespace App\Http\Controllers;

use App\Models\TermsVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Términos y condiciones versionados (Plan 03, ADR-0031).
 *
 * La aceptación es del USUARIO, no del hogar: vive fuera del multi-tenant
 * y solo alcanza al autenticado — no hay IDs ajenos en las URLs, así que
 * la autorización es el propio middleware 'auth'.
 */
class TermsController extends Controller
{
    /**
     * Versión vigente, lectura pública.
     */
    public function show(): View
    {
        $version = TermsVersion::current();

        abort_if($version === null, 404);

        return view('terms.show', ['version' => $version, 'historical' => false]);
    }

    /**
     * Versión histórica, lectura pública: la aceptación guarda el
     * identificador de la versión, y esta URL es su referencia externa.
     */
    public function version(TermsVersion $termsVersion): View
    {
        return view('terms.show', [
            'version' => $termsVersion,
            'historical' => ! $termsVersion->is(TermsVersion::current()),
        ]);
    }

    /**
     * Pantalla de aceptación de la vigente. Si ya la aceptó (o no hay
     * versión publicada) no hay nada que decidir: al panel.
     */
    public function acceptForm(Request $request): View|RedirectResponse
    {
        $version = TermsVersion::current();

        if ($version === null || $request->user()->hasAcceptedCurrentTerms()) {
            return redirect()->route('dashboard');
        }

        // Última versión aceptada, para contextualizar "qué cambió".
        $lastAccepted = $request->user()->acceptedTerms()
            ->with('termsVersion')
            ->latest('accepted_at')
            ->first()
            ?->termsVersion;

        return view('terms.accept', [
            'version' => $version,
            'lastAccepted' => $lastAccepted,
        ]);
    }

    /**
     * Registra la aceptación: versión exacta, fecha y IP (prueba de
     * consentimiento, Ley 1581). Idempotente en el modelo.
     */
    public function accept(Request $request): RedirectResponse
    {
        $version = TermsVersion::current();

        if ($version === null) {
            return redirect()->route('dashboard');
        }

        $request->user()->acceptTerms($version, $request->ip());

        return redirect()->route('dashboard')
            ->with('status', __('¡Gracias! Ya puedes seguir usando Finlia.'));
    }

    /**
     * "No aceptar": pantalla honesta de salida. NO destruye nada — ni
     * cuenta, ni datos, ni siquiera la sesión. Las acciones de exportar
     * datos y eliminar cuenta llegan con los planes 05/06 y enlazarán aquí.
     */
    public function reject(): View
    {
        return view('terms.exit');
    }
}
