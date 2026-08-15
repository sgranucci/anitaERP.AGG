/**
 * Modo consulta — propagación automática del flag ?vista=consulta
 *
 * Cuando una solapa fue abierta como consulta (URL contiene vista=consulta y el
 * layout aplicó la clase body.modo-consulta), todos los links/forms/peticiones
 * que generen una nueva URL del mismo origen deben heredar el flag.
 *
 * Objetivo de negocio: que el usuario pueda trabajar con la consulta abierta y
 * sus consultas anidadas (sub-consultas relacionadas, edición permitida, listas
 * vinculadas) sin poder saltar a otros módulos vía menú lateral (que ya está
 * oculto cuando el layout renderiza en modo consulta).
 *
 * Expone window.ModoConsulta con utilidades para SEMBRAR el flag desde
 * botones iniciales (pantallas que no están en modo consulta pero abren
 * solapas que sí deben estarlo).
 */
(function () {
    'use strict';

    var FLAG_NAME = 'vista';
    var FLAG_VALUE = 'consulta';

    function esMismoOrigen(url) {
        if (!url) return false;
        var lower = String(url).toLowerCase().trim();
        if (lower.indexOf('mailto:') === 0) return false;
        if (lower.indexOf('tel:') === 0) return false;
        if (lower.indexOf('javascript:') === 0) return false;
        if (lower.indexOf('data:') === 0) return false;
        if (lower.indexOf('blob:') === 0) return false;
        if (lower.indexOf('#') === 0) return false;
        try {
            var u = new URL(url, window.location.href);
            return u.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function agregarFlagAUrl(url) {
        if (url == null || url === '' || url === '#') return url;
        if (!esMismoOrigen(url)) return url;
        try {
            var u = new URL(url, window.location.href);
            if (u.searchParams.get(FLAG_NAME) === FLAG_VALUE) return url;
            u.searchParams.set(FLAG_NAME, FLAG_VALUE);
            if (/^https?:\/\//i.test(url)) {
                return u.toString();
            }
            return u.pathname + (u.search || '') + (u.hash || '');
        } catch (e) {
            return url;
        }
    }

    // API pública: usar desde scripts que arrancan una consulta para sembrar
    // el flag en la URL inicial. La propagación automática se encarga del
    // resto una vez cargada la solapa.
    window.ModoConsulta = {
        FLAG_NAME: FLAG_NAME,
        FLAG_VALUE: FLAG_VALUE,
        url: agregarFlagAUrl,
        activo: function () {
            return !!(document.body && document.body.classList.contains('modo-consulta'));
        },
    };

    if (!document.body || !document.body.classList.contains('modo-consulta')) {
        return;
    }

    // Cerrar solapa: window.close() solo funciona si la pestaña la abrió un script.
    // Si el navegador lo bloquea (navegación misma pestaña / target=_blank+noopener),
    // volver atrás al listado/origen.
    window.cerrarSolapaConsulta = function () {
        try {
            window.close();
        } catch (e) {}
        setTimeout(function () {
            try {
                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }
            } catch (e2) {}
            alert('No se pudo cerrar la solapa automáticamente. Cierre esta pestaña del navegador o use Atrás.');
        }, 150);
    };

    document.addEventListener('click', function (e) {
        var el = e.target;
        if (!el) return;
        if (el.closest) {
            el = el.closest('button, a');
        }
        if (!el) return;
        var onclick = el.getAttribute && el.getAttribute('onclick');
        if (!onclick || onclick.indexOf('window.close') === -1) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        window.cerrarSolapaConsulta();
    }, true);

    function debeIgnorarAnchor(a) {
        if (!a || a.tagName !== 'A') return true;
        var href = a.getAttribute('href');
        if (!href) return true;
        var lower = href.toLowerCase().trim();
        if (lower.indexOf('#') === 0) return true;
        if (lower.indexOf('mailto:') === 0) return true;
        if (lower.indexOf('tel:') === 0) return true;
        if (lower.indexOf('javascript:') === 0) return true;
        if (a.hasAttribute('download')) return true;
        if (a.dataset && a.dataset.modoConsultaOmitir === '1') return true;
        return false;
    }

    function procesarAnchor(a) {
        if (debeIgnorarAnchor(a)) return;
        var href = a.getAttribute('href');
        if (!esMismoOrigen(href)) return;
        var nuevo = agregarFlagAUrl(href);
        if (nuevo !== href) {
            a.setAttribute('href', nuevo);
        }
    }

    function procesarForm(f) {
        if (!f || f.tagName !== 'FORM') return;
        if (f.dataset && f.dataset.modoConsultaOmitir === '1') return;
        var action = f.getAttribute('action') || window.location.href;
        if (!esMismoOrigen(action)) return;
        var nuevoAction = agregarFlagAUrl(action);
        if (nuevoAction !== action) {
            f.setAttribute('action', nuevoAction);
        }
        if (!f.querySelector('input[name="' + FLAG_NAME + '"][data-modo-consulta="1"]')) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = FLAG_NAME;
            inp.value = FLAG_VALUE;
            inp.setAttribute('data-modo-consulta', '1');
            f.appendChild(inp);
        }
    }

    function procesarRaiz(raiz) {
        if (!raiz) return;
        if (raiz.nodeType !== 1 && raiz.nodeType !== 9) return;
        var anchors = raiz.querySelectorAll ? raiz.querySelectorAll('a[href]') : [];
        for (var i = 0; i < anchors.length; i++) procesarAnchor(anchors[i]);
        var forms = raiz.querySelectorAll ? raiz.querySelectorAll('form') : [];
        for (var j = 0; j < forms.length; j++) procesarForm(forms[j]);
        if (raiz.tagName === 'A') procesarAnchor(raiz);
        if (raiz.tagName === 'FORM') procesarForm(raiz);
    }

    procesarRaiz(document);

    if (typeof MutationObserver === 'function') {
        var obs = new MutationObserver(function (mutaciones) {
            for (var k = 0; k < mutaciones.length; k++) {
                var m = mutaciones[k];
                if (m.type === 'childList') {
                    for (var n = 0; n < m.addedNodes.length; n++) {
                        procesarRaiz(m.addedNodes[n]);
                    }
                } else if (m.type === 'attributes' && m.target && m.target.tagName === 'A' && m.attributeName === 'href') {
                    procesarAnchor(m.target);
                } else if (m.type === 'attributes' && m.target && m.target.tagName === 'FORM' && m.attributeName === 'action') {
                    procesarForm(m.target);
                }
            }
        });
        obs.observe(document.documentElement || document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['href', 'action'],
        });
    }

    var openOriginal = window.open;
    window.open = function (url, target, features) {
        try {
            if (url && esMismoOrigen(url)) {
                url = agregarFlagAUrl(url);
            }
        } catch (e) {}
        return openOriginal.call(window, url, target, features);
    };

    try {
        var xhrOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url) {
            try {
                if (url && esMismoOrigen(url)) {
                    arguments[1] = agregarFlagAUrl(url);
                }
            } catch (e) {}
            return xhrOpen.apply(this, arguments);
        };
    } catch (e) {}

    try {
        if (typeof window.fetch === 'function') {
            var fetchOriginal = window.fetch;
            window.fetch = function (input, init) {
                try {
                    if (typeof input === 'string' && esMismoOrigen(input)) {
                        input = agregarFlagAUrl(input);
                    } else if (input && typeof input === 'object' && input.url && esMismoOrigen(input.url)) {
                        input = new Request(agregarFlagAUrl(input.url), input);
                    }
                } catch (e) {}
                return fetchOriginal.call(window, input, init);
            };
        }
    } catch (e) {}
})();
