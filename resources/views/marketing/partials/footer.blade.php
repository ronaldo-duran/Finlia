<footer class="marketing-footer">
    <div class="container">
        <div class="row g-4 pb-4">
            <div class="col-12 col-md-5">
                <div class="d-inline-flex align-items-center gap-2 mb-2">
                    <x-brandmark :size="26" />
                    <span class="fs-6 fw-semibold">Finlia</span>
                </div>
                <p class="text-secondary mb-0" style="max-width: 38ch;">
                    Finanzas personales y familiares. Hecho en Colombia, en pesos y en español.
                </p>
            </div>

            <div class="col-6 col-md-3">
                <h2 class="titulo-footer">Producto</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="#como-funciona" class="enlace-footer">Cómo funciona</a></li>
                    <li><a href="#funciones" class="enlace-footer">Funciones</a></li>
                    <li><a href="{{ route('register') }}" class="enlace-footer">Crear cuenta</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-4">
                <h2 class="titulo-footer">Legal y código</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('terms.show') }}" class="enlace-footer">Términos y condiciones</a></li>
                    <li><a href="{{ route('data.policy') }}" class="enlace-footer">Tus datos y Finlia</a></li>
                    {{-- AGPL §13: quien usa Finlia como servicio tiene derecho al
                         código correspondiente. Este enlace es la forma de cumplirlo. --}}
                    <li>
                        <a href="https://github.com/ronaldo-duran/Finlia" class="enlace-footer" rel="noopener">
                            Código fuente <i class="bi bi-box-arrow-up-right small"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pie-legal d-flex flex-wrap justify-content-between gap-2">
            <span>© {{ now()->year }} Finlia · v{{ config('finlia.version') }}</span>
            <span>
                Software libre bajo
                <a href="https://www.gnu.org/licenses/agpl-3.0.html" class="enlace-footer" rel="license noopener">AGPL-3.0</a>
            </span>
        </div>
    </div>
</footer>
