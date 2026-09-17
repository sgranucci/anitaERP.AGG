/**
 * Liquidación de tareas OT: overlay de descarga + activación de consultas.
 * Cliente: onClienteElegidoEnConsulta evita que leeUnCliente pise #cliente_id global.
 */
(function () {
    'use strict';

    var OVERLAY_ID = 'liquidacion-tarea-overlay';
    var TITULO_ID = 'liquidacion-tarea-overlay-titulo';
    var SUBTITULO_ID = 'liquidacion-tarea-overlay-subtitulo';
    var OVERLAY_IMPORT_ID = 'overlay-importar-tareas-l8-liquidacion';
    var ptrClienteCampo = $();

    function overlay() {
        return document.getElementById(OVERLAY_ID);
    }

    function mostrar(titulo, subtitulo) {
        var ov = overlay();
        if (!ov) {
            return;
        }
        var t = document.getElementById(TITULO_ID);
        var s = document.getElementById(SUBTITULO_ID);
        if (t && titulo) {
            t.textContent = titulo;
        }
        if (s && subtitulo) {
            s.textContent = subtitulo;
        }
        ov.classList.remove('d-none');
        ov.style.display = 'flex';
        ov.setAttribute('aria-hidden', 'false');
    }

    function ocultar() {
        var ov = overlay();
        if (!ov) {
            return;
        }
        ov.classList.add('d-none');
        ov.style.display = '';
        ov.setAttribute('aria-hidden', 'true');
    }

    function overlayImport() {
        return document.getElementById(OVERLAY_IMPORT_ID);
    }

    function mostrarImport() {
        var ov = overlayImport();
        if (!ov) {
            return;
        }
        ov.classList.remove('d-none');
        ov.style.display = 'flex';
        ov.setAttribute('aria-hidden', 'false');
    }

    function ocultarImport() {
        var ov = overlayImport();
        if (!ov) {
            return;
        }
        ov.classList.add('d-none');
        ov.style.display = '';
        ov.setAttribute('aria-hidden', 'true');
    }

    function overlayVisible() {
        var ov = overlay();
        var ovImp = overlayImport();
        return (ov && !ov.classList.contains('d-none'))
            || (ovImp && !ovImp.classList.contains('d-none'));
    }

    function sincronizarFechasImport() {
        var desde = document.getElementById('desdefecha');
        var hasta = document.getElementById('hastafecha');
        var hDesde = document.getElementById('import_l8_desdefecha');
        var hHasta = document.getElementById('import_l8_hastafecha');
        if (desde && hDesde) {
            hDesde.value = desde.value || '';
        }
        if (hasta && hHasta) {
            hHasta.value = hasta.value || '';
        }
    }

    function avanzarNextFocus($campo) {
        var next = $campo.attr('data-next-focus');
        if (next) {
            $(next).trigger('focus');
        }
    }

    function aplicarClienteEnCampo($campo, data) {
        if (!$campo || !$campo.length || !data) {
            return;
        }
        $campo.find('.cliente_id').first().val(data.id || '');
        $campo.find('.codigocliente').first().val(data.codigo || '');
        $campo.find('.nombrecliente').first().val(data.nombre || '');
    }

    function limpiarClienteEnCampo($campo) {
        if (!$campo || !$campo.length) {
            return;
        }
        $campo.find('.cliente_id').first().val('');
        $campo.find('.codigocliente').first().val('');
        $campo.find('.nombrecliente').first().val('');
    }

    function resolverClientePorCodigo($campo, codigo, avisar) {
        codigo = String(codigo || '').trim();
        if (codigo === '') {
            limpiarClienteEnCampo($campo);
            return;
        }
        $.get(carpetaBase + '/ventas/leerunclienteporcodigo/' + encodeURIComponent(codigo))
            .done(function (data) {
                if (data && data.id) {
                    aplicarClienteEnCampo($campo, data);
                    return;
                }
                limpiarClienteEnCampo($campo);
                if (avisar) {
                    setTimeout(function () {
                        alert('No se encontró el cliente indicado.');
                    }, 0);
                    $campo.find('.codigocliente').first().trigger('focus');
                }
            })
            .fail(function () {
                limpiarClienteEnCampo($campo);
                if (avisar) {
                    setTimeout(function () {
                        alert('No se pudo cargar el cliente.');
                    }, 0);
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Intercepta elección del modal compartido de cliente.
        window.onClienteElegidoEnConsulta = function (fila) {
            var $campo = ptrClienteCampo.length
                ? ptrClienteCampo
                : (typeof ptrcliente_id !== 'undefined' && ptrcliente_id && ptrcliente_id.length
                    ? ptrcliente_id.closest('.tm-cliente-campo')
                    : $());
            if (!$campo.length || !$('#form-general').find($campo).length) {
                return false;
            }
            aplicarClienteEnCampo($campo, fila);
            avanzarNextFocus($campo);
            return true;
        };

        if (typeof activa_eventos_consultacliente === 'function') {
            activa_eventos_consultacliente();
        }
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        if (typeof activa_eventos_consultatarea === 'function') {
            activa_eventos_consultatarea();
        }
        if (typeof activa_eventos_consultaempleado === 'function') {
            activa_eventos_consultaempleado();
        }

        $(document)
            .on('click.liqTareaCliente', '#form-general .tm-cliente-campo .consultacliente', function () {
                ptrClienteCampo = $(this).closest('.tm-cliente-campo');
            })
            .on('keydown.liqTareaClienteF1', '#form-general .tm-cliente-campo .codigocliente', function (e) {
                if (e.key === 'F1' || e.keyCode === 112) {
                    ptrClienteCampo = $(this).closest('.tm-cliente-campo');
                }
            })
            .on('keydown.liqTareaClienteEnter', '#form-general .tm-cliente-campo .codigocliente', function (e) {
                if (e.key !== 'Enter' && e.keyCode !== 13) {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                var $campo = $(this).closest('.tm-cliente-campo');
                ptrClienteCampo = $campo;
                resolverClientePorCodigo($campo, $(this).val(), true);
                setTimeout(function () {
                    avanzarNextFocus($campo);
                }, 80);
            })
            .on('keydown.liqTareaArtNext', '#form-general .tm-articulo-campo .codigoarticulo', function (e) {
                if (e.key !== 'Enter' && e.keyCode !== 13) {
                    return;
                }
                var $campo = $(this).closest('.tm-articulo-campo');
                setTimeout(function () {
                    avanzarNextFocus($campo);
                }, 80);
            });

        var form = document.getElementById('form-general');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                mostrar(
                    'Generando reporte…',
                    'Puede demorar según el período. Pulse Esc si la descarga ya terminó.'
                );
                window.addEventListener('focus', ocultar, { once: true });
            });
        }

        var formImport = document.getElementById('form-importar-tareas-l8-liquidacion');
        if (formImport) {
            formImport.addEventListener('submit', function (e) {
                sincronizarFechasImport();
                var desde = (document.getElementById('import_l8_desdefecha') || {}).value || '';
                var hasta = (document.getElementById('import_l8_hastafecha') || {}).value || '';
                if (!desde || !hasta) {
                    e.preventDefault();
                    alert('Indique Desde fecha y Hasta fecha en el formulario.');
                    return;
                }
                var msg = '¿Traer de L8 las tareas del ' + desde + ' al ' + hasta
                    + '?\n\n• Inserta las que faltan en el ERP (sin duplicar)\n'
                    + '• Actualiza fechas de finalización de las ya cargadas';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                    return;
                }
                mostrarImport();
            });
        }

        window.addEventListener('pageshow', function () {
            ocultar();
            ocultarImport();
        });
        window.addEventListener('pagehide', function () {
            ocultar();
            ocultarImport();
        });

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && overlayVisible()) {
                ocultar();
                ocultarImport();
            }
        });
    });
})();
