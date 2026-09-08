{{--
    Barra de progreso de navegación.

    Finlia navega con recargas completas: entre el clic y la página nueva no
    hay ninguna señal. Si el servidor tarda, el usuario cree que la app se
    pegó y vuelve a pulsar — y en un POST eso duplica la acción.

    Va vacía y a cero: solo aparece cuando `window.Finlia.cargando` la
    enciende, y siempre con 140 ms de retardo para que una respuesta rápida
    no produzca un parpadeo. Se apaga sola al pintarse la página nueva.

    `aria-hidden`: es una señal visual redundante — el estado de espera se
    anuncia con `aria-busy` en el botón que se pulsó.
--}}
<div class="finlia-progress" id="finliaProgress" aria-hidden="true"></div>
