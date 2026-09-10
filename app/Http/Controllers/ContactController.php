<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContactReason;
use App\Http\Requests\Contact\StoreBugReportRequest;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Dos entradas al mismo buzón:
 *
 *  - `/contacto`        sitio público, sin sesión, con selector de motivo.
 *  - `/reportar-error`  dentro de la aplicación, con sesión y contexto técnico.
 *
 * Controlador fino: valida (Form Request), llama al Service y responde
 * (ADR-0010). El límite de envíos vive en las rutas.
 */
class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contact) {}

    public function create(): View
    {
        return view('marketing.contacto', [
            'motivos' => ContactReason::public(),
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $this->contact->record(
            reason: ContactReason::from($request->string('reason')->toString()),
            name: $request->string('name')->trim()->toString(),
            email: $request->string('email')->trim()->toString(),
            body: $request->string('body')->trim()->toString(),
            userId: $request->user()?->id,
            ipAddress: $request->ip(),
        );

        return redirect()
            ->route('contact.create')
            ->with('status', 'Recibimos tu mensaje. Te respondemos al correo que dejaste.');
    }

    public function createBugReport(): View
    {
        return view('contact.reportar-error');
    }

    public function storeBugReport(StoreBugReportRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        $this->contact->record(
            reason: ContactReason::Bug,
            name: $usuario->name,
            email: $usuario->email,
            body: $request->string('body')->trim()->toString(),
            userId: $usuario->id,
            context: $request->context(),
        );

        return redirect()
            ->route('dashboard')
            ->with('status', 'Gracias por avisar. Revisamos el reporte y te escribimos si necesitamos más detalles.');
    }
}
