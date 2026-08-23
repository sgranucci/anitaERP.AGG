(function () {
    'use strict';

    function mostrarOverlay() {
        var overlay = document.getElementById('impresion-sesion-overlay');
        if (overlay) {
            overlay.hidden = false;
        }
    }

    function iniciar() {
        var form = document.getElementById('form-ejecutar-sesion');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function () {
            var boton = document.getElementById('btn-ejecutar-sesion');
            if (boton) {
                boton.disabled = true;
            }
            mostrarOverlay();
        });

        if (window.impresionSesionAuto) {
            mostrarOverlay();
            form.submit();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
