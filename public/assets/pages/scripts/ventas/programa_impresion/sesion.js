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

        function sincronizarEnviarImpresora() {
            var tilde = document.getElementById('sesion_enviar_impresora');
            var oculto = document.getElementById('input-enviar-impresora');
            if (oculto) {
                oculto.value = (tilde && tilde.checked) ? '1' : '0';
            }
        }

        var tildeImpresora = document.getElementById('sesion_enviar_impresora');
        if (tildeImpresora) {
            tildeImpresora.addEventListener('change', sincronizarEnviarImpresora);
        }

        form.addEventListener('submit', function (ev) {
            sincronizarEnviarImpresora();
            var cajas = checks();
            var soloCopia = document.getElementById('input-solo-copia');
            var esSoloCopia = soloCopia && String(soloCopia.value) === '1';
            var enviarImpresora = document.getElementById('input-enviar-impresora');
            var vaAImpresora = !enviarImpresora || String(enviarImpresora.value) === '1';
            if (cajas.length > 0 && !esSoloCopia && vaAImpresora) {
                var alguna = false;
                cajas.forEach(function (c) {
                    if (c.checked) {
                        alguna = true;
                    }
                });
                if (!alguna) {
                    ev.preventDefault();
                    window.alert('Elegí al menos una copia de papel (por ejemplo Original y Triplicado). El NAS se archiva aparte.');
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

        document.querySelectorAll('.btn-ejecutar-sesion').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var resetSolo = document.getElementById('input-solo-copia');
                if (resetSolo) {
                    resetSolo.value = '0';
                }
            });
        });

        document.querySelectorAll('.btn-solo-copia').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var idx = String(boton.getAttribute('data-pack-idx') || '');
                var soloCopia = document.getElementById('input-solo-copia');
                if (soloCopia) {
                    soloCopia.value = '1';
                }
                checks().forEach(function (c) {
                    c.checked = c.value === idx;
                });
                var yaEsta = false;
                checks().forEach(function (c) {
                    if (c.value === idx) {
                        yaEsta = true;
                    }
                });
                if (!yaEsta) {
                    var oculto = document.createElement('input');
                    oculto.type = 'hidden';
                    oculto.name = 'pack_idx[]';
                    oculto.value = idx;
                    oculto.className = 'sesion-copia-idx-nas';
                    form.appendChild(oculto);
                }
                var enviarOculto = document.getElementById('input-enviar-impresora');
                if (enviarOculto && !boton.classList.contains('btn-outline-warning')) {
                    enviarOculto.value = '1';
                }
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

        document.querySelectorAll('.link-descargar-pdf-sesion').forEach(function (link) {
            link.addEventListener('click', function (ev) {
                var cajas = checks();
                if (cajas.length === 0) {
                    return;
                }
                var idxs = [];
                cajas.forEach(function (c) {
                    if (c.checked) {
                        idxs.push(c.value);
                    }
                });
                if (!idxs.length) {
                    ev.preventDefault();
                    window.alert('Elegí al menos una copia de papel para el PDF.');
                    return;
                }
                ev.preventDefault();
                var url = new URL(link.getAttribute('href'), window.location.href);
                url.searchParams.delete('pack_idx[]');
                idxs.forEach(function (idx) {
                    url.searchParams.append('pack_idx[]', idx);
                });
                window.location = url.toString();
            });
        });

        if (window.impresionSesionAuto) {
            sincronizarEnviarImpresora();
            var ocultoAuto = document.getElementById('input-enviar-impresora');
            if (ocultoAuto && String(ocultoAuto.value) !== '1') {
                return;
            }
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
