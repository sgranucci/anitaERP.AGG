(function () {
    'use strict';

    var overlay = document.getElementById('ajuste-inflacion-overlay');
    var titulo = document.getElementById('ajuste-inflacion-overlay-titulo');

    function mostrarOverlay(texto) {
        if (!overlay) {
            return;
        }
        if (titulo && texto) {
            titulo.textContent = texto;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.form-proceso-ajuste').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }
            mostrarOverlay(form.getAttribute('data-titulo') || 'Procesando ajuste por inflación…');
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);
})();
