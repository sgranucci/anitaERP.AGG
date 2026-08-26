(function () {
    'use strict';

    function mostrarOverlay() {
        var overlay = document.getElementById('impresion-sesion-overlay');
        if (overlay) {
            overlay.hidden = false;
        }
    }

    function checks() {
        return document.querySelectorAll('.sesion-copia-idx');
    }

    function iniciar() {
        var form = document.getElementById('form-ejecutar-sesion');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (ev) {
            var cajas = checks();
            if (cajas.length > 0) {
                var alguna = false;
                cajas.forEach(function (c) {
                    if (c.checked) {
                        alguna = true;
                    }
                });
                if (!alguna) {
                    ev.preventDefault();
                    window.alert('Elegí al menos una copia (por ejemplo Original y Triplicado).');
                    return;
                }
            }
            document.querySelectorAll('.btn-ejecutar-sesion').forEach(function (boton) {
                boton.disabled = true;
            });
            document.querySelectorAll('.btn-solo-copia').forEach(function (b) {
                b.disabled = true;
            });
            mostrarOverlay();
        });

        document.querySelectorAll('.btn-solo-copia').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var idx = String(boton.getAttribute('data-pack-idx') || '');
                checks().forEach(function (c) {
                    c.checked = c.value === idx;
                });
                form.submit();
            });
        });

        document.querySelectorAll('.sesion-marcar-copias').forEach(function (enlace) {
            enlace.addEventListener('click', function (ev) {
                ev.preventDefault();
                var marcar = enlace.getAttribute('data-marcar') === '1';
                checks().forEach(function (c) {
                    c.checked = marcar;
                });
            });
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
