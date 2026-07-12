(function () {
    'use strict';

    function overlayEl() {
        return document.getElementById('arca-caea-informe-overlay');
    }

    function mostrarOverlay(titulo, subtitulo) {
        var el = overlayEl();
        if (!el) {
            return;
        }
        var t = document.getElementById('arca-caea-informe-overlay-titulo');
        var s = document.getElementById('arca-caea-informe-overlay-subtitulo');
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s) {
            s.textContent = subtitulo || 'El proceso corre en segundo plano. Al terminar recibirás un mail.';
        }
        el.classList.remove('d-none');
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.classList || !form.classList.contains('js-arca-caea-informar-form')) {
            return;
        }
        if (form.querySelector('button[type="submit"]:disabled')) {
            ev.preventDefault();
            return;
        }
        var titulo = form.getAttribute('data-overlay-titulo') || 'Presentando comprobantes CAEA…';
        var subtitulo = form.getAttribute('data-overlay-subtitulo') || '';
        mostrarOverlay(titulo, subtitulo);
    }, true);
})();
