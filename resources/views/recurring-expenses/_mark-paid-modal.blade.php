{{-- Modal compartido "marcar pagado" (recurrentes y recordatorios).
     Se abre desde botones con data-* (action, name, amount, date, has-account).
     Un solo form con dos submits (data-register) — sin lógica AJAX. --}}
<div class="modal fade" id="markPaidModal" tabindex="-1" aria-labelledby="markPaidModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="markPaidForm" method="POST" action="">
                @csrf
                <input type="hidden" name="register" id="markPaidRegister" value="1">

                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title fw-semibold" id="markPaidModalLabel">
                        <i class="bi bi-check2-circle me-1 text-success"></i>
                        Marcar como pagado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-1">
                        <strong id="markPaidName"></strong>
                    </p>
                    <p class="text-muted small mb-3">
                        Vencía el <span id="markPaidDate"></span> · <span id="markPaidAmount"></span>
                    </p>

                    <div id="markPaidWithAccount">
                        <p class="mb-0">
                            ¿Registrar el gasto en la cuenta asociada y avanzar la próxima fecha,
                            o ya lo pagaste por fuera de Finlia?
                        </p>
                    </div>

                    <div id="markPaidNoAccount" class="alert alert-info small mb-0 d-none">
                        Este recurrente no tiene cuenta asociada, así que no se puede
                        registrar el movimiento automáticamente. Puedes avanzar la
                        próxima fecha y, si quieres verlo en movimientos, registrarlo
                        manualmente desde <a href="{{ route('expenses.create') }}">Registrar gasto</a>.
                    </div>
                </div>

                <div class="modal-footer border-0 pt-1 d-flex flex-wrap gap-2 justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" data-register="0">
                        Solo avanzar fecha
                    </button>
                    <button type="submit" class="btn btn-sm btn-finlia" id="markPaidRegisterBtn" data-register="1">
                        <i class="bi bi-check-lg me-1"></i> Sí, registrar y avanzar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('markPaidModal');
    if (!modal) return;

    var form    = document.getElementById('markPaidForm');
    var regEl   = document.getElementById('markPaidRegister');
    var nameEl  = document.getElementById('markPaidName');
    var amtEl   = document.getElementById('markPaidAmount');
    var dateEl  = document.getElementById('markPaidDate');
    var withAcc = document.getElementById('markPaidWithAccount');
    var noAcc   = document.getElementById('markPaidNoAccount');
    var regBtn  = document.getElementById('markPaidRegisterBtn');

    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;

        // textContent en todo: los data-* son datos escapados por Blade,
        // nunca se interpolan como HTML.
        form.action        = btn.getAttribute('data-action') || '';
        nameEl.textContent = btn.getAttribute('data-name') || '';
        amtEl.textContent  = btn.getAttribute('data-amount') || '';
        dateEl.textContent = btn.getAttribute('data-date') || '';

        var hasAccount = btn.getAttribute('data-has-account') === '1';
        withAcc.classList.toggle('d-none', !hasAccount);
        noAcc.classList.toggle('d-none', hasAccount);
        regBtn.classList.toggle('d-none', !hasAccount);

        // Sin cuenta, el único camino es "solo avanzar": el hidden ya
        // arranca en 0 para que un doble Enter no dispare un registrar
        // que el backend no podría cumplir de todos modos.
        regEl.value = hasAccount ? '1' : '0';
    });

    // Cada submit lleva su intención en data-register: se copia al hidden
    // justo antes de que el form llegue al interceptor de progreso.
    form.addEventListener('click', function (e) {
        var btn = e.target.closest('button[type="submit"][data-register]');
        if (!btn) return;
        regEl.value = btn.getAttribute('data-register');
    });
})();
</script>
@endpush
