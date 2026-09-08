{{--
    Aviso de instalación para iOS.

    Safari nunca implementó `beforeinstallprompt`, así que en iPhone/iPad no
    existe el aviso nativo de instalación: la única vía es explicarle al
    usuario los pasos del menú Compartir. Esto es ese instructivo.

    No se muestra cuando:
      · no es iOS (Android y escritorio ya tienen el aviso nativo del navegador);
      · la app ya corre instalada (`navigator.standalone`), donde sobraría;
      · el usuario lo descartó antes.

    Ojo con un detalle que se pasa por alto: en iOS **solo Safari** instala de
    verdad. Chrome, Firefox y Edge son WebKit por obligación de Apple, pero su
    "Añadir a pantalla de inicio" crea un marcador, no una app en standalone.
    Por eso a esos navegadores se les pide abrirlo en Safari en vez de darles
    unos pasos que no les van a funcionar.

    El banner va en el flujo normal del documento (no `fixed`): así empuja a la
    navbar hacia abajo sin pelear por z-index ni obligar a recalcular paddings,
    y se va con el scroll mientras la navbar se queda pegada.
--}}
<div class="ios-install-banner d-none" id="iosInstallBanner" role="region" aria-label="Instalar Finlia">
    <div class="ios-install-banner-inner">
        <x-brandmark :size="22" class="flex-shrink-0" />

        <div class="ios-install-banner-text">
            <strong>Instala Finlia</strong>
            <span id="iosInstallBannerHint">Ábrela como una app</span>
        </div>

        <button type="button" class="btn btn-sm btn-finlia flex-shrink-0" id="iosInstallOpen">
            Ver cómo
        </button>

        <button type="button" class="btn-icon flex-shrink-0" id="iosInstallDismiss" aria-label="No mostrar más">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>

{{-- Pasos. Se abre desde el banner; el contenido cambia según el navegador. --}}
<div class="modal fade" id="iosInstallModal" tabindex="-1" aria-labelledby="iosInstallModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-semibold" id="iosInstallModalLabel">
                    <i class="bi bi-phone me-1 text-finlia"></i>
                    Instalar Finlia en tu iPhone
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                {{-- Safari: los tres pasos reales --}}
                <div id="iosInstallStepsSafari">
                    <p class="text-muted small mb-3">
                        Se abre a pantalla completa, sin la barra del navegador, y queda
                        junto a tus otras apps.
                    </p>

                    <ol class="ios-install-steps">
                        {{-- El botón Compartir es un icono sin etiqueta, así que
                             mucha gente no lo identifica por el nombre. Se muestra
                             en grande para que lo reconozca por la forma.

                             Su posición NO es fija: abajo en iPhone con la barra
                             de direcciones abajo (lo normal desde iOS 15), arriba
                             en iPad y en iPhone si el usuario movió la barra. Por
                             eso el texto no promete una sola ubicación. --}}
                        <li>
                            <span class="ios-install-step-n">1</span>
                            <span>
                                Toca este botón en la barra de Safari:
                                <span class="ios-install-icon-demo">
                                    <i class="bi bi-box-arrow-up" aria-hidden="true"></i>
                                </span>
                                <small class="d-block text-muted mt-1">
                                    Es <strong>Compartir</strong>. Suele estar abajo; en iPad
                                    —o si tienes la barra arriba— está en la parte superior.
                                </small>
                            </span>
                        </li>
                        <li>
                            <span class="ios-install-step-n">2</span>
                            <span>
                                Desliza y elige
                                <i class="bi bi-plus-square me-1" aria-hidden="true"></i><strong>Añadir a pantalla de inicio</strong>.
                            </span>
                        </li>
                        <li>
                            <span class="ios-install-step-n">3</span>
                            <span>Confirma con <strong>Añadir</strong>. Listo.</span>
                        </li>
                    </ol>
                </div>

                {{-- Chrome/Firefox/Edge en iOS: su "añadir" crea un marcador, no una app --}}
                <div id="iosInstallStepsOtro" class="d-none">
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3 small mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle mt-1 flex-shrink-0"></i>
                        <span>
                            En iPhone solo <strong>Safari</strong> puede instalar la app.
                            Desde este navegador se crearía un acceso directo que
                            volvería a abrirse dentro del navegador.
                        </span>
                    </div>

                    <ol class="ios-install-steps">
                        <li>
                            <span class="ios-install-step-n">1</span>
                            <span>Abre <strong>{{ config('app.url') }}</strong> en <strong>Safari</strong>.</span>
                        </li>
                        <li>
                            <span class="ios-install-step-n">2</span>
                            <span>
                                Allí toca <strong>Compartir</strong>
                                <i class="bi bi-box-arrow-up mx-1" aria-hidden="true"></i>
                                y luego <strong>Añadir a pantalla de inicio</strong>.
                            </span>
                        </li>
                    </ol>
                </div>
            </div>

            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                    Ahora no
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var CLAVE_DESCARTADO = 'finlia_ios_install_dismissed';

    var banner = document.getElementById('iosInstallBanner');
    if (!banner) return;

    var ua = navigator.userAgent || '';

    // iPadOS 13+ se presenta como Mac: se distingue por el soporte táctil.
    var esIOS = /iPad|iPhone|iPod/.test(ua)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Ya instalada: `standalone` es la propiedad de iOS; el media query cubre
    // al resto por si este partial se reutiliza algún día.
    var yaInstalada = window.navigator.standalone === true
        || window.matchMedia('(display-mode: standalone)').matches;

    // En iOS todos los navegadores son WebKit, así que no vale detectar el
    // motor: hay que mirar el envoltorio concreto.
    var esSafari = !/CriOS|FxiOS|EdgiOS|OPiOS|GSA/i.test(ua);

    var descartado = false;
    try { descartado = localStorage.getItem(CLAVE_DESCARTADO) === '1'; } catch (e) { /* modo privado */ }

    if (!esIOS || yaInstalada || descartado) return;

    if (!esSafari) {
        document.getElementById('iosInstallStepsSafari').classList.add('d-none');
        document.getElementById('iosInstallStepsOtro').classList.remove('d-none');
        document.getElementById('iosInstallBannerHint').textContent = 'Ábrelo en Safari para instalarla';
    }

    banner.classList.remove('d-none');

    document.getElementById('iosInstallOpen').addEventListener('click', function () {
        if (!window.bootstrap) return;
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('iosInstallModal')).show();
    });

    document.getElementById('iosInstallDismiss').addEventListener('click', function () {
        banner.remove();
        try { localStorage.setItem(CLAVE_DESCARTADO, '1'); } catch (e) { /* modo privado: reaparecerá */ }
    });
})();
</script>
@endpush
