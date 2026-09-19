{{--
    Árbol de decisión de compras (Épica 12, v0.39).

    Se abre si `session('compulsive_survey_expense_id')` está seteado — que
    ocurre en `ExpenseController::store` con la probabilidad y el cupo del
    hogar. Cuatro preguntas cortas de opción única, con opción de saltar.
--}}
@php
    $surveyExpenseId = session('compulsive_survey_expense_id');
@endphp

@if ($surveyExpenseId)
    <div class="modal fade" id="compulsiveSurveyModal" tabindex="-1"
         aria-labelledby="compulsiveSurveyModalLabel" aria-hidden="true"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('expenses.survey.store', ['expense' => $surveyExpenseId]) }}">
                    @csrf
                    <div class="modal-header">
                        <h1 class="h5 modal-title" id="compulsiveSurveyModalLabel">
                            <i class="bi bi-lightbulb me-1"></i> Un gasto fuera del presupuesto
                        </h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Saltar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Este gasto no cabe en ningún presupuesto planeado para su categoría este mes.
                            Cuatro preguntas rapidísimas ahora, y a los 30 días te preguntamos cómo te
                            sientes con esa compra. Todo es opcional — puedes saltar en cualquier momento.
                        </p>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">¿Este gasto lo tenías previsto?</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach (\App\Enums\CompulsivePlanned::options() as $value => $label)
                                    <div>
                                        <input type="radio" class="btn-check" name="planned" id="planned_{{ $value }}"
                                               value="{{ $value }}" required>
                                        <label class="btn btn-outline-secondary btn-sm" for="planned_{{ $value }}">
                                            {{ $label }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">¿Cómo lo llamarías?</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach (\App\Enums\CompulsiveKind::options() as $value => $label)
                                    <div>
                                        <input type="radio" class="btn-check" name="kind" id="kind_{{ $value }}"
                                               value="{{ $value }}" required>
                                        <label class="btn btn-outline-secondary btn-sm" for="kind_{{ $value }}">
                                            {{ $label }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">¿Cómo te sientes ahora?</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach (\App\Enums\CompulsiveMood::cases() as $case)
                                    <div>
                                        <input type="radio" class="btn-check" name="mood" id="mood_{{ $case->value }}"
                                               value="{{ $case->value }}" required>
                                        <label class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1"
                                               for="mood_{{ $case->value }}" title="{{ $case->label() }}">
                                            <i class="bi {{ $case->iconClass() }} fs-5"></i>
                                            <span class="d-none d-sm-inline small">{{ $case->label() }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label fw-semibold small">¿Qué lo disparó?</label>
                            <select name="trigger" class="form-select form-select-sm" required>
                                <option value="">Elige una opción…</option>
                                @foreach (\App\Enums\CompulsiveTrigger::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link btn-sm text-secondary" data-bs-dismiss="modal">
                            Ahora no
                        </button>
                        <button type="submit" class="btn btn-finlia btn-sm">
                            <i class="bi bi-check-lg me-1"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    {{--
        Se espera a DOMContentLoaded porque `@vite` emite `<script type="module">`,
        que es deferred: el bundle que expone `window.bootstrap` no ha corrido cuando
        este script inline se ejecuta durante el parsing. `DOMContentLoaded` dispara
        DESPUÉS de los módulos deferred, con `window.bootstrap` ya disponible.
    --}}
    <script>
        (function () {
            function abrir() {
                var modalEl = document.getElementById('compulsiveSurveyModal');
                if (!modalEl || !window.bootstrap) return;
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', abrir);
            } else {
                abrir();
            }
        })();
    </script>
    @endpush
@endif
