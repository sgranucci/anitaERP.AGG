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

        var btnImpresora = document.getElementById('btn-guardar-impresora-sesion');
        if (btnImpresora) {
            btnImpresora.addEventListener('click', function () {
                var select = document.getElementById('sesion_salida_id');
                var salidaId = select ? String(select.value || '') : '';
                if (!salidaId) {
                    window.alert('Seleccione una impresora.');
                    return;
                }
                var base = btnImpresora.getAttribute('data-url-setear') || '';
                if (!base) {
                    return;
                }
                var disparar = document.getElementById('sesion_disparar_al_grabar');
                var uri = base + '/' + encodeURIComponent(salidaId)
                    + '?disparar_al_grabar=' + (disparar && disparar.checked ? '1' : '0');
                btnImpresora.disabled = true;
                fetch(uri, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('No se pudo guardar la impresora.');
                        }
                        window.location.reload();
                    })
                    .catch(function (err) {
                        btnImpresora.disabled = false;
                        window.alert(err.message || 'No se pudo guardar la impresora.');
                    });
            });
        }

        if (window.impresionSesionAuto) {
            if (window.impresionSesionFaltaImpresora) {
                window.alert('Elegí tu impresora en Mi impresora antes de imprimir las copias de papel.');
                return;
            }
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
