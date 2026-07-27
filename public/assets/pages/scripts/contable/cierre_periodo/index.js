(function () {
    'use strict';

    function mostrarOverlay(titulo) {
        var overlay = document.getElementById('cierre-periodo-overlay');
        if (!overlay) {
            return;
        }
        var tituloEl = document.getElementById('cierre-periodo-overlay-titulo');
        if (titulo && tituloEl) {
            tituloEl.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById('cierre-periodo-overlay');
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.form-proceso-cierre').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (form.checkValidity && !form.checkValidity()) {
                    return;
                }
                var titulo = form.getAttribute('data-overlay-titulo') || 'Procesando…';
                mostrarOverlay(titulo);
            });
        });

        window.addEventListener('pageshow', function () {
            ocultarOverlay();
        });
    });
})();
