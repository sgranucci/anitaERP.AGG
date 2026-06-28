(function (window, document) {
    'use strict';

    var OVERLAY_ID = 'av-gastro-procesando-overlay';
    var TITULO_ID = 'av-gastro-procesando-titulo';
    var SUBTITULO_ID = 'av-gastro-procesando-subtitulo';
    var STORAGE_KEY = 'av_gastro_articulos_vendidos_procesando';
    var TITULO_DEFAULT = 'Procesando reporte…';
    var SUBTITULO_DEFAULT = 'Artículos vendidos gastronomía. Por favor espere.';

    function escHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function esUrlNavegacionReporte(href) {
        if (!href || href === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }

        try {
            var url = new URL(href, window.location.origin);
            var path = url.pathname.toLowerCase();

            if (path.indexOf('listar-gastronomia-articulos-vendidos') !== -1) {
                return true;
            }

            if (path.indexOf('articulos-vendidos') === -1) {
                return false;
            }

            return path.indexOf('articulos-vendidos/api') === -1;
        } catch (e) {
            var lower = String(href).toLowerCase();
            return lower.indexOf('articulos-vendidos') !== -1
                && lower.indexOf('articulos-vendidos/api') === -1;
        }
    }

    function overlayElement() {
        return document.getElementById(OVERLAY_ID);
    }

    function ensureOverlay() {
        var overlay = overlayElement();
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.id = OVERLAY_ID;
        overlay.className = 'd-none';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'assertive');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2050;display:flex;align-items:center;justify-content:center;padding:1.25rem;pointer-events:all;';
        overlay.innerHTML = ''
            + '<div class="alert alert-warning shadow-lg mb-0 text-center px-4 py-3 border border-warning"'
            + ' style="max-width:96vw;min-width:20rem;font-size:1rem;">'
            + '<i class="fa fa-spinner fa-spin fa-2x text-danger mb-2 d-block" aria-hidden="true"></i>'
            + '<strong id="' + TITULO_ID + '">' + escHtml(TITULO_DEFAULT) + '</strong>'
            + '<div class="small mt-2" id="' + SUBTITULO_ID + '">' + escHtml(SUBTITULO_DEFAULT) + '</div>'
            + '</div>';

        document.body.appendChild(overlay);
        return overlay;
    }

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = ensureOverlay();
        var tituloEl = document.getElementById(TITULO_ID);
        var subtituloEl = document.getElementById(SUBTITULO_ID);

        if (tituloEl) {
            tituloEl.textContent = titulo || TITULO_DEFAULT;
        }
        if (subtituloEl) {
            subtituloEl.textContent = subtitulo || SUBTITULO_DEFAULT;
        }

        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        try {
            window.sessionStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            // ignorar
        }
    }

    function ocultarOverlay() {
        var overlay = overlayElement();
        if (overlay) {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';

        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // ignorar
        }
    }

    function marcarNavegacionReporte() {
        mostrarOverlay(TITULO_DEFAULT, SUBTITULO_DEFAULT);
    }

    function initNavegacionGlobal() {
        document.addEventListener('click', function (event) {
            var enlace = event.target && event.target.closest
                ? event.target.closest('a[href]')
                : null;

            if (!enlace || enlace.target === '_blank' || enlace.hasAttribute('download')) {
                return;
            }

            if (!esUrlNavegacionReporte(enlace.getAttribute('href') || enlace.href)) {
                return;
            }

            marcarNavegacionReporte();
        }, true);

        window.addEventListener('pageshow', function () {
            ocultarOverlay();
        });
    }

    function initPaginaReporte() {
        ocultarOverlay();

        var form = document.getElementById('form-filtros-articulos-vendidos');
        if (form) {
            form.addEventListener('submit', function () {
                marcarNavegacionReporte();
            });
        }

        document.addEventListener('click', function (event) {
            var enlace = event.target && event.target.closest
                ? event.target.closest('a[href]')
                : null;

            if (!enlace || enlace.target === '_blank') {
                return;
            }

            if (enlace.closest('.pagination') && esUrlNavegacionReporte(enlace.getAttribute('href') || enlace.href)) {
                marcarNavegacionReporte();
            }
        }, false);
    }

    window.ArticulosVendidosProcesando = {
        mostrar: mostrarOverlay,
        ocultar: ocultarOverlay,
        esUrlNavegacionReporte: esUrlNavegacionReporte,
    };

    initNavegacionGlobal();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaginaReporte);
    } else {
        initPaginaReporte();
    }
}(window, document));
