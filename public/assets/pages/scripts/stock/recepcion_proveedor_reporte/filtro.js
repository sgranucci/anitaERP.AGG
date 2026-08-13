/**
 * Overlay del informe de recepción de proveedores.
 * El partial trae display:flex inline: hay que forzar none !important al ocultar.
 */
(function () {
    'use strict';

    var hintTimer = null;

    function overlayEl() {
        return document.getElementById('rpr-overlay');
    }

    function rprMostrarOverlay(titulo, subtitulo) {
        var overlay = overlayEl();
        if (!overlay) {
            return;
        }
        if (titulo) {
            var t = document.getElementById('rpr-overlay-titulo');
            if (t) {
                t.textContent = titulo;
            }
        }
        if (subtitulo) {
            var s = document.getElementById('rpr-overlay-subtitulo');
            if (s) {
                s.textContent = subtitulo;
            }
        }
        overlay.classList.remove('d-none');
        overlay.style.setProperty('display', 'flex', 'important');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (hintTimer) {
            clearTimeout(hintTimer);
        }
        hintTimer = setTimeout(function () {
            var s = document.getElementById('rpr-overlay-subtitulo');
            if (s && overlay.getAttribute('aria-hidden') === 'false') {
                s.textContent = 'Sigue en curso… Pulse Esc para cerrar el aviso (no cancela la consulta).';
            }
        }, 90000);
    }

    function rprOcultarOverlay() {
        if (hintTimer) {
            clearTimeout(hintTimer);
            hintTimer = null;
        }
        var overlay = overlayEl();
        if (!overlay) {
            document.body.style.overflow = '';
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.setProperty('display', 'none', 'important');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function init() {
        rprOcultarOverlay();

        var form = document.getElementById('form-recepcion-proveedor-reporte');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                rprMostrarOverlay(
                    'Consultando recepciones…',
                    'Puede demorar según el período. No cierre la página.'
                );
            });
        }

        document.querySelectorAll('a[href*="listar-reporte-recepcion-proveedor"]').forEach(function (a) {
            a.addEventListener('click', function () {
                rprMostrarOverlay(
                    'Exportando…',
                    'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.'
                );
                window.addEventListener('focus', rprOcultarOverlay, { once: true });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('pageshow', rprOcultarOverlay);
    window.addEventListener('pagehide', rprOcultarOverlay);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            rprOcultarOverlay();
        }
    });
})();
