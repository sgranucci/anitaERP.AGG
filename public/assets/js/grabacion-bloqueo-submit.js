/**
 * Bloqueo global de reenvío de formularios + banner "Grabando…".
 *
 * Incidente 21/ago/2026: solicitud de pago grabada 3 veces (códigos 11333/11334/11335,
 * a 4 y 5 segundos una de otra) porque el alta tarda —escribe en Anita y dispara el
 * árbol— y nada impedía volver a apretar Guardar.
 *
 * Cubre las dos vías de envío que usa el ERP:
 *  - submit real del navegador (click en botón submit, Enter) → listener en captura
 *  - $('#form-general').submit() de los `.botonsubmit` → jQuery no dispara listeners
 *    nativos: termina llamando a HTMLFormElement.prototype.submit, parcheado acá
 *
 * No interfiere con las validaciones de pantalla: si un handler hace preventDefault
 * (campos obligatorios, asiento sin balancear, submit por AJAX) no se bloquea nada.
 *
 * Por formulario:
 *  - data-sin-bloqueo-grabacion  → excluye el formulario del bloqueo y del banner
 *  - data-mensaje-grabacion="Confirmando…" → texto del banner
 */
(function () {
    'use strict';

    var ID_OVERLAY = 'grabacion-overlay-global';
    var ATTR_EN_CURSO = 'data-anita-grabando';
    var ATTR_BOTON = 'data-anita-grabando-boton';
    var MS_AVISO_DEMORA = 45000;
    var TEXTO_DEFECTO = 'Grabando\u2026';

    var formularioEnCurso = null;
    var temporizadorDemora = null;
    var subtituloOriginal = null;

    function overlay() {
        return document.getElementById(ID_OVERLAY);
    }

    function elementoTitulo() {
        return document.getElementById(ID_OVERLAY + '-titulo');
    }

    function elementoSubtitulo() {
        return document.getElementById(ID_OVERLAY + '-subtitulo');
    }

    function textoBanner(form) {
        var propio = form ? form.getAttribute('data-mensaje-grabacion') : '';
        return propio && propio.trim() !== '' ? propio : TEXTO_DEFECTO;
    }

    /** Otro proceso ya tapó la pantalla con su propio overlay (requisición, exportaciones, etc.). */
    function hayOtroOverlayVisible() {
        var nodos = document.querySelectorAll('[id*="overlay"], [class*="overlay"]');
        for (var i = 0; i < nodos.length; i++) {
            var el = nodos[i];
            if (el.id === ID_OVERLAY || el.classList.contains('d-none')) {
                continue;
            }
            if (el.offsetWidth <= 0 || el.offsetHeight <= 0) {
                continue;
            }
            if (window.getComputedStyle(el).position === 'fixed') {
                return true;
            }
        }
        return false;
    }

    function mostrarOverlay(titulo) {
        var ov = overlay();
        if (!ov) {
            return;
        }
        var t = elementoTitulo();
        if (t && titulo) {
            t.textContent = titulo;
        }
        ov.classList.remove('d-none');
        ov.style.display = 'flex';
        ov.setAttribute('aria-hidden', 'false');
        programarAvisoDemora();
    }

    function ocultarOverlay() {
        var ov = overlay();
        cancelarAvisoDemora();
        if (!ov) {
            return;
        }
        ov.classList.add('d-none');
        ov.style.display = '';
        ov.setAttribute('aria-hidden', 'true');
    }

    function cancelarAvisoDemora() {
        if (temporizadorDemora) {
            window.clearTimeout(temporizadorDemora);
            temporizadorDemora = null;
        }
    }

    function programarAvisoDemora() {
        cancelarAvisoDemora();
        temporizadorDemora = window.setTimeout(mostrarAvisoDemora, MS_AVISO_DEMORA);
    }

    /**
     * El banner no se cierra solo: liberarlo automáticamente habilita de nuevo el
     * doble envío. Pasado un rato se ofrece salida manual por si el pedido se cortó.
     */
    function mostrarAvisoDemora() {
        var sub = elementoSubtitulo();
        if (!sub || sub.getAttribute('data-aviso-demora') === '1') {
            return;
        }
        if (subtituloOriginal === null) {
            subtituloOriginal = sub.textContent;
        }
        sub.setAttribute('data-aviso-demora', '1');
        sub.textContent = 'Está tardando más de lo normal. No vuelva a grabar: el registro puede haberse creado igual. '
            + 'Revise el listado antes de reintentar.';

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-outline-dark btn-sm mt-2';
        boton.textContent = 'Liberar pantalla';
        boton.addEventListener('click', liberar);
        sub.appendChild(document.createElement('br'));
        sub.appendChild(boton);
    }

    function escaparSelector(valor) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(valor);
        }
        return String(valor).replace(/["\\\]]/g, '\\$&');
    }

    function botonesDeEnvio(form) {
        var selector = 'button[type="submit"], input[type="submit"], input[type="image"], button:not([type])';
        var botones = Array.prototype.slice.call(form.querySelectorAll(selector));
        if (form.id) {
            var externos = document.querySelectorAll('[form="' + escaparSelector(form.id) + '"]');
            Array.prototype.forEach.call(externos, function (el) {
                var tipo = (el.getAttribute('type') || '').toLowerCase();
                var esBoton = el.nodeName === 'BUTTON' || el.nodeName === 'INPUT';
                if (esBoton && (tipo === '' || tipo === 'submit' || tipo === 'image')) {
                    botones.push(el);
                }
            });
        }
        return botones;
    }

    function bloquearBotones(form, bloquear) {
        botonesDeEnvio(form).forEach(function (boton) {
            if (bloquear) {
                boton.setAttribute(ATTR_BOTON, '1');
                boton.disabled = true;
                boton.classList.add('disabled');
                return;
            }
            if (boton.getAttribute(ATTR_BOTON) === '1') {
                boton.removeAttribute(ATTR_BOTON);
                boton.disabled = false;
                boton.classList.remove('disabled');
            }
        });
    }

    function esFormularioDeGrabado(form) {
        if (!form || form.nodeName !== 'FORM') {
            return false;
        }
        if (form.hasAttribute('data-sin-bloqueo-grabacion')) {
            return false;
        }
        // GET = consulta / filtro / export: reenviarlo no duplica nada.
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') {
            return false;
        }
        // Si abre en otra pestaña, esta pantalla no navega y el banner quedaría pegado.
        var target = (form.getAttribute('target') || '').toLowerCase();
        return target === '' || target === '_self';
    }

    function estaGrabando(form) {
        return !!form && form.getAttribute(ATTR_EN_CURSO) === '1';
    }

    /** Mientras un envío está en vuelo, ningún otro formulario de la pantalla puede salir. */
    function hayEnvioEnCurso() {
        return formularioEnCurso !== null;
    }

    function marcarGrabando(form) {
        if (!form || estaGrabando(form)) {
            return false;
        }
        form.setAttribute(ATTR_EN_CURSO, '1');
        formularioEnCurso = form;
        document.body.setAttribute('aria-busy', 'true');
        if (!hayOtroOverlayVisible()) {
            mostrarOverlay(textoBanner(form));
        }
        // Deshabilitar recién en el próximo tick: si el botón tiene name/value,
        // el navegador ya lo incluyó en el envío que está saliendo.
        window.setTimeout(function () {
            bloquearBotones(form, true);
        }, 0);

        return true;
    }

    function liberar() {
        if (formularioEnCurso) {
            formularioEnCurso.removeAttribute(ATTR_EN_CURSO);
            bloquearBotones(formularioEnCurso, false);
            formularioEnCurso = null;
        }
        document.body.removeAttribute('aria-busy');
        var sub = elementoSubtitulo();
        if (sub && sub.getAttribute('data-aviso-demora') === '1') {
            sub.removeAttribute('data-aviso-demora');
            sub.textContent = subtituloOriginal === null ? '' : subtituloOriginal;
        }
        ocultarOverlay();
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!esFormularioDeGrabado(form)) {
            return;
        }

        if (hayEnvioEnCurso()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            mostrarOverlay(textoBanner(formularioEnCurso));

            return;
        }

        // Las validaciones de pantalla corren después (están enganchadas al formulario)
        // y pueden cancelar el envío; en el próximo tick ya se sabe si sale de verdad.
        window.setTimeout(function () {
            if (!event.defaultPrevented) {
                marcarGrabando(form);
            }
        }, 0);
    }, true);

    // jQuery .submit()/.trigger('submit') no dispara listeners nativos: termina acá.
    var submitNativo = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (esFormularioDeGrabado(this)) {
            if (hayEnvioEnCurso()) {
                mostrarOverlay(textoBanner(formularioEnCurso));

                return undefined;
            }
            marcarGrabando(this);
        }

        return submitNativo.apply(this, arguments);
    };

    // Volver con «atrás» restaura la pantalla desde caché con el banner puesto.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted || formularioEnCurso) {
            liberar();
        }
    });

    window.AnitaGrabacion = {
        mostrar: mostrarOverlay,
        ocultar: ocultarOverlay,
        liberar: liberar,
        marcar: marcarGrabando,
        enCurso: function () {
            return formularioEnCurso !== null;
        },
    };
})();
