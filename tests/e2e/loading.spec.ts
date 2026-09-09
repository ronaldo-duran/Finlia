import { test, expect, Page } from '@playwright/test';

/**
 * Indicadores de carga.
 *
 * Finlia navega con recargas completas: entre el clic y la página nueva no
 * hay ninguna señal. Con el servidor lento el usuario cree que la app se
 * pegó y vuelve a pulsar — y en un POST eso duplica la acción.
 *
 * La técnica de casi todos los tests es cancelar la navegación en vez de
 * simular un servidor lento. El listener que la cancela se registra DESPUÉS
 * del módulo, así que el módulo ve el evento intacto y aplica su lógica de
 * verdad; la cancelación solo evita que la página se vaya, y con ella el
 * indicador que queremos mirar. Como consecuencia **ningún test de este
 * fichero llega al servidor ni toca datos**.
 */

/**
 * Deja el evento visible para el módulo, pero sin navegar.
 *
 * Clic y envío van por separado a propósito: cancelar el **clic** de un botón
 * de tipo submit cancela también el envío del formulario, y entonces el
 * evento `submit` no llega a dispararse — que es justo lo que el test quiere
 * observar.
 */
async function sinNavegar(page: Page, evento: 'click' | 'submit') {
  await page.evaluate((tipo) => {
    document.addEventListener(tipo, (e) => e.preventDefault());
  }, evento);
}

const barraDe = (page: Page) => page.locator('#finliaProgress');

test.describe('Indicadores de carga', () => {
  test('la barra superior aparece al navegar', async ({ page }) => {
    await page.goto('/dashboard');
    await sinNavegar(page, 'click');

    await page.getByRole('link', { name: 'Movimientos' }).first().click();

    await expect(barraDe(page)).toHaveClass(/is-activa/);
  });

  /**
   * El retardo es la diferencia entre una señal y un parpadeo: si la
   * respuesta llega en 80 ms, una barra que aparece y desaparece es ruido.
   * Se comprueba en el mismo tick síncrono para no depender del reloj.
   */
  test('no aparece de inmediato, sino cuando la espera se nota', async ({ page }) => {
    await page.goto('/dashboard');

    const visibleAlInstante = await page.evaluate(() => {
      (window as any).Finlia.cargando.iniciar();

      return document.getElementById('finliaProgress')!.classList.contains('is-activa');
    });

    expect(visibleAlInstante).toBe(false);
    await expect(barraDe(page)).toHaveClass(/is-activa/);
  });

  test('el botón pulsado muestra un spinner y queda inerte', async ({ page }) => {
    // Alta de hogar: es el formulario válido que menos depende de lo
    // sembrado. El de gastos exige cuenta y categoría, y el hogar activo
    // cambia según qué specs hayan corrido antes.
    await page.goto('/hogares/crear');
    await sinNavegar(page, 'submit');

    await page.fill('input[name="name"]', 'Hogar de prueba de carga');

    const boton = page.getByRole('button', { name: 'Crear hogar' });
    const anchoAntes = (await boton.boundingBox())!.width;

    await boton.click();

    await expect(boton).toHaveClass(/is-cargando/);
    await expect(boton).toHaveAttribute('aria-busy', 'true');
    await expect(barraDe(page)).toHaveClass(/is-activa/);

    // Este botón tiene icono, así que el spinner ocupa su sitio y la etiqueta
    // se queda: "⟳ Crear hogar" dice qué está pasando, mientras que un botón
    // vacío con una ruedita se lee como si algo hubiera fallado.
    await expect(boton.locator('.finlia-btn-spinner-inline')).toBeVisible();
    await expect(boton).toContainText('Crear hogar');

    // El icono se sustituye, no se suma: si se sumara, el botón crecería y
    // desplazaría al "Cancelar" de al lado justo al pulsarlo.
    expect((await boton.boundingBox())!.width).toBeCloseTo(anchoAntes, 0);
  });

  /**
   * Un botón sin icono no tiene nada que sustituir, así que se oculta su
   * contenido entero — y `visibility: hidden` lo saca del árbol de
   * accesibilidad. Sin el aria-label de respaldo, un lector de pantalla
   * anunciaría "botón, ocupado" y nada más.
   */
  test('un botón sin icono conserva su nombre mientras espera', async ({ page }) => {
    await page.goto('/dashboard');

    const estado = await page.evaluate(() => {
      const boton = document.createElement('button');
      boton.type = 'submit';
      boton.className = 'btn';
      boton.textContent = 'Confirmar todo';
      document.body.appendChild(boton);

      (window as any).Finlia.cargando.ocuparBoton(boton);

      return {
        nombreAccesible: boton.getAttribute('aria-label'),
        spinnerCentrado: !!boton.querySelector('.finlia-btn-spinner-centrado'),
        textoOculto: !!boton.querySelector('span.invisible'),
      };
    });

    expect(estado.nombreAccesible).toBe('Confirmar todo');
    expect(estado.spinnerCentrado).toBe(true);
    expect(estado.textoOculto).toBe(true);
  });

  /**
   * Lo que de verdad protege al usuario: sin esto, dos toques impacientes
   * sobre "Guardar gasto" son dos gastos.
   *
   * Se cuentan los envíos que habrían llegado al servidor — los que salen del
   * módulo sin cancelar — en vez de contar peticiones, que aquí no se hacen.
   */
  test('descarta el segundo envío del mismo formulario', async ({ page }) => {
    await page.goto('/hogares/crear');
    await page.fill('input[name="name"]', 'Hogar de prueba de carga');

    const envios = await page.evaluate(() => {
      let n = 0;
      document.addEventListener('submit', (e) => {
        if (!e.defaultPrevented) n++;
        e.preventDefault();
      });

      const form = document.querySelector<HTMLFormElement>('form[action$="/hogares"]')!;
      form.requestSubmit();
      form.requestSubmit();
      form.requestSubmit();

      return n;
    });

    expect(envios).toBe(1);
    await expect(barraDe(page)).toHaveClass(/is-activa/);
  });

  /**
   * El modal de confirmación envía con `HTMLFormElement.prototype.submit()`
   * para no re-disparar su propio interceptor — y eso se salta el evento
   * `submit`, así que el indicador no se enciende solo y hubo que pedirlo a
   * mano. Este test existe porque el borrado es justo la acción en la que
   * más se espera, y porque ese `submit()` no se puede cancelar: aquí sí se
   * intercepta la petición, y se aborta para que no llegue a la BD.
   */
  test('el borrado confirmado en el modal también avisa', async ({ page }) => {
    await page.goto('/dashboard');
    // 204: el navegador se queda en esta página en vez de navegar. Abortar
    // no sirve — llevaría a la página de error de Chrome, y con ella se iría
    // la barra que queremos comprobar.
    await page.route('**/ruta-de-prueba-borrado', (route) => route.fulfill({ status: 204 }));

    // Formulario propio: las categorías del seeder son globales y no se
    // pueden borrar, y no queremos que el test dependa de qué hay sembrado.
    await page.evaluate(() => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/ruta-de-prueba-borrado';
      form.setAttribute('data-confirm', '¿Eliminar esto?');
      form.innerHTML = '<button type="submit">Eliminar</button>';
      document.body.appendChild(form);
    });

    await page.getByRole('button', { name: 'Eliminar' }).click();
    await expect(page.locator('#confirmModal')).toBeVisible();
    await page.getByRole('button', { name: 'Sí, continuar' }).click();

    await expect(barraDe(page)).toHaveClass(/is-activa/);
  });

  /**
   * Volver atrás restaura la página tal como se dejó — con la barra a medias
   * y el botón girando para siempre — si nadie limpia en `pageshow`.
   *
   * Se dispara el evento a mano en vez de navegar y volver: la bfcache no
   * siempre entra en headless, y un test que no puede fallar no protege nada.
   */
  test('al restaurarse desde la caché de atrás no queda nada girando', async ({ page }) => {
    await page.goto('/hogares/crear');

    const estado = await page.evaluate(() => {
      const barra = document.getElementById('finliaProgress')!;
      const boton = document.querySelector<HTMLButtonElement>('form[action$="/hogares"] button[type="submit"]')!;
      const form = boton.closest('form')!;

      // Se deja la página en el estado exacto en el que se abandona al enviar.
      (window as any).Finlia.cargando.ocuparFormulario(form, boton);
      barra.classList.add('is-activa');

      window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));

      return {
        barraActiva: barra.classList.contains('is-activa'),
        botonesOcupados: document.querySelectorAll('.is-cargando').length,
        formularioBloqueado: form.dataset.finliaEnviando === '1',
        // El contenido tiene que volver a su sitio: si el envoltorio oculto
        // se quedara puesto, el botón seguiría invisible aunque ya no gire.
        restosDelSpinner: boton.querySelectorAll('.finlia-btn-spinner, span.invisible').length,
      };
    });

    expect(estado.barraActiva).toBe(false);
    expect(estado.botonesOcupados).toBe(0);
    expect(estado.formularioBloqueado).toBe(false);
    expect(estado.restosDelSpinner).toBe(0);
  });

  /**
   * Un enlace que abre otra pestaña deja esta página donde está: encender la
   * barra ahí la dejaría girando para siempre sobre una pantalla que nunca
   * cambia. Lo mismo vale para anclas y descargas.
   */
  test('ignora los enlaces que no cambian esta página', async ({ page }) => {
    await page.goto('/dashboard');

    const resultado = await page.evaluate(async () => {
      document.addEventListener('click', (e) => e.preventDefault());

      const barra = document.getElementById('finliaProgress')!;
      const cargando = (window as any).Finlia.cargando;

      const clicEn = (attrs: Record<string, string>) => {
        const a = document.createElement('a');
        Object.entries(attrs).forEach(([k, v]) => a.setAttribute(k, v));
        a.textContent = 'enlace';
        document.body.appendChild(a);
        a.click();
      };

      // Se espera más que el retardo de 140 ms antes de mirar.
      const seEncendio = async () => {
        await new Promise((r) => setTimeout(r, 400));
        const activa = barra.classList.contains('is-activa');
        cargando.detener();

        return activa;
      };

      clicEn({ href: '/movimientos' });
      const interno = await seEncendio();

      clicEn({ href: '/movimientos', target: '_blank' });
      const otraPestana = await seEncendio();

      clicEn({ href: '#seccion' });
      const ancla = await seEncendio();

      clicEn({ href: '/exportar.zip', download: '' });
      const descarga = await seEncendio();

      return { interno, otraPestana, ancla, descarga };
    });

    // El caso positivo va en el mismo test: si el módulo dejara de
    // encenderse nunca, los tres negativos pasarían igual y no nos
    // enteraríamos.
    expect(resultado.interno).toBe(true);
    expect(resultado.otraPestana).toBe(false);
    expect(resultado.ancla).toBe(false);
    expect(resultado.descarga).toBe(false);
  });
});
