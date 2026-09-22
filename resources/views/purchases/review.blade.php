@extends('layouts.app', ['title' => 'Compras a revisar'])

@section('content')
    <x-flash-messages />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <h1 class="h3 mb-0"><i class="bi bi-arrow-counterclockwise me-2"></i>Compras a revisar</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-link text-secondary">
            <i class="bi bi-arrow-left me-1"></i> Al panel
        </a>
    </div>
    <p class="text-muted mb-4">
        Compras que respondiste en el árbol de decisión y ya cumplieron un mes.
        Contarnos cómo te sientes ahora cierra la conversación con vos mismo.
    </p>

    @if ($pending->isEmpty())
        <div class="card border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-check2-circle text-success" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">No tienes compras pendientes de revisar.</p>
            </div>
        </div>
    @else
        <div class="d-flex flex-column gap-3">
            @foreach ($pending as $response)
                @php
                    $expense = $response->expense;
                    $categoryName = $expense?->category?->name ?? 'Sin categoría';
                    $moodBefore = $response->mood;
                @endphp

                <div class="card border-0">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">
                                    @money($expense?->amount ?? 0)
                                    <span class="text-muted small">· {{ $categoryName }}</span>
                                </div>
                                <div class="text-muted small">
                                    {{ $expense?->description ?: 'Sin descripción' }}
                                </div>
                                <div class="text-muted small mt-1">
                                    Comprado el
                                    {{ optional($expense?->date)->format('d/m/Y') ?: optional($response->created_at)->format('d/m/Y') }}.
                                    Cuando lo registraste te sentías
                                    <i class="bi {{ $moodBefore->iconClass() }}"></i>
                                    <strong>{{ strtolower($moodBefore->label()) }}</strong>.
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('purchases.review.store', $response) }}"
                              class="mt-3">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">¿Cómo te sientes con esa compra ahora?</label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach (\App\Enums\CompulsiveMood::cases() as $case)
                                        <div>
                                            <input type="radio" class="btn-check"
                                                   name="mood_after"
                                                   id="mood_after_{{ $response->id }}_{{ $case->value }}"
                                                   value="{{ $case->value }}" required>
                                            <label class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1"
                                                   for="mood_after_{{ $response->id }}_{{ $case->value }}"
                                                   title="{{ $case->label() }}">
                                                <i class="bi {{ $case->iconClass() }} fs-5"></i>
                                                <span class="d-none d-sm-inline small">{{ $case->label() }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">¿Te arrepientes de haberla hecho?</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <div>
                                        <input type="radio" class="btn-check"
                                               name="regret" id="regret_{{ $response->id }}_no"
                                               value="0">
                                        <label class="btn btn-outline-secondary btn-sm"
                                               for="regret_{{ $response->id }}_no">No</label>
                                    </div>
                                    <div>
                                        <input type="radio" class="btn-check"
                                               name="regret" id="regret_{{ $response->id }}_yes"
                                               value="1">
                                        <label class="btn btn-outline-secondary btn-sm"
                                               for="regret_{{ $response->id }}_yes">Sí</label>
                                    </div>
                                    <div>
                                        <input type="radio" class="btn-check"
                                               name="regret" id="regret_{{ $response->id }}_skip"
                                               value="" checked>
                                        <label class="btn btn-outline-secondary btn-sm"
                                               for="regret_{{ $response->id }}_skip">Prefiero no decirlo</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="note_{{ $response->id }}"
                                       class="form-label fw-semibold small">
                                    Notas (opcional)
                                </label>
                                <textarea class="form-control form-control-sm" rows="2"
                                          id="note_{{ $response->id }}" name="note"
                                          maxlength="500"
                                          placeholder="¿La estás usando? ¿Valió la pena?"></textarea>
                            </div>

                            <button type="submit" class="btn btn-finlia btn-sm">
                                <i class="bi bi-check-lg me-1"></i> Guardar
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
