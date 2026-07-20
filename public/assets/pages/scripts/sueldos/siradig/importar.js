(function () {
    'use strict';

    var overlay = document.getElementById('siradig-import-overlay');

    function mostrar() {
        if (!overlay) {
            return;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-importar-siradig');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }
            mostrar();
        });
    });

    window.addEventListener('pageshow', ocultar);
})();
