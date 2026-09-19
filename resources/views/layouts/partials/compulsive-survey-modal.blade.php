{{--
    Árbol de decisión de compras (Épica 12, v0.38).

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
                            <i class="bi bi-lightbulb me-1"></i> Un momento contigo
                        </h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Saltar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Cuatro preguntas rapidísimas sobre el gasto que acabas de registrar.
                            Nos ayudan a mostrarte con el tiempo un espejo de tus compras. Puedes saltar en cualquier momento.
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
                                        <label class="btn btn-outline-secondary btn-sm" for="mood_{{ $case->value }}"
                                               title="{{ $case->label() }}">
                                            <span style="font-size: 1.3rem;">{{ $case->emoji() }}</span>
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
    <script>
        (function () {
            var modalEl = document.getElementById('compulsiveSurveyModal');
            if (!modalEl || !window.bootstrap) return;
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        })();
    </script>
    @endpush
@endif
