(function (window, $) {
    'use strict';

    var estadoPadron = {
        clienteId: null,
        ok: null,
        mensaje: null,
        estadoCliente: null,
    };

    var SELECTOR_BOTONES_FACTURA = '[data-padron-accion-factura]';
    var SELECTOR_AVISO_PADRON = '#aviso-padron-operacion-pedido, #aviso-padron-operacion-factura';
    var SELECTOR_CONTENIDO_CARGA = '#pedido-carga-contenido, #factura-carga-contenido';
    var CLASE_CAMPOS_BLOQUEABLES = '.pedido-carga-bloqueable, .factura-carga-bloqueable';
    var CLASE_FORM_BLOQUEADO = 'ventas-bloqueado-padron';

    function requiereValidacionPadronSeleccion() {
        return window.REQUIERE_VALIDACION_PADRON_OPERACION === true;
    }

    function requiereValidacionPadronPostCarga() {
        return window.VALIDACION_PADRON_POST_CARGA === true;
    }

    function carpeta() {
        return typeof carpetaBase !== 'undefined' ? carpetaBase : '';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        var input = document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function mensajeDesdeXhr(xhr) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }

        return 'Problemas en ARCA: no se puede operar con este cliente.';
    }

    function clienteIdDesdeFormulario() {
        var val = $('#cliente_id').val();
        if (val === null || val === undefined || val === '') {
            return 0;
        }

        return parseInt(val, 10);
    }

    function esClienteRegularizado(estado) {
        return String(estado || '').toUpperCase() === 'R';
    }

    function actualizarAvisoPadronOperacion(texto, tipo) {
        var $aviso = $(SELECTOR_AVISO_PADRON);
        if (!$aviso.length) {
            return;
        }

        $aviso.each(function () {
            var $el = $(this);
            if (!texto) {
                $el.addClass('d-none').removeClass('alert-warning alert-danger alert-success').text('');
                return;
            }

            $el.removeClass('d-none alert-warning alert-danger alert-success');
            if (tipo === 'ok') {
                $el.addClass('alert-success');
            } else if (tipo === 'bloqueo') {
                $el.addClass('alert-danger');
            } else {
                $el.addClass('alert-warning');
            }
            $el.text(texto);
        });
    }

    function aplicarBloqueoCargaVentas(bloquear, motivo) {
        if (!requiereValidacionPadronPostCarga()) {
            return;
        }

        var $form = $('#formgeneral');
        if (!$form.length) {
            return;
        }

        var $campos = $form.find(CLASE_CAMPOS_BLOQUEABLES);
        $(SELECTOR_CONTENIDO_CARGA).each(function () {
            $campos = $campos.add($(this).find(
                'input:not([type="hidden"]):not([readonly]), select, textarea, button'
            ));
        });

        $campos.prop('disabled', !!bloquear);
        $form.toggleClass(CLASE_FORM_BLOQUEADO, !!bloquear);
        $form.toggleClass('pedido-bloqueado-padron', !!bloquear);

        if (bloquear) {
            actualizarAvisoPadronOperacion(
                motivo || 'Problemas en ARCA: no puede cargar ítems con este cliente.',
                'bloqueo'
            );
        } else {
            actualizarAvisoPadronOperacion('', null);
        }
    }

    function actualizarBotonesFacturaSegunPadron() {
        if (!requiereValidacionPadronSeleccion() && !requiereValidacionPadronPostCarga()) {
            $(SELECTOR_BOTONES_FACTURA).prop('disabled', false).removeClass('disabled').removeAttr('title');
            return;
        }

        var id = clienteIdDesdeFormulario();
        var bloqueado = id > 0 && estadoPadron.clienteId === id && estadoPadron.ok === false;
        var pendiente = id > 0 && (estadoPadron.clienteId !== id || estadoPadron.ok === null);

        $(SELECTOR_BOTONES_FACTURA).each(function () {
            var $btn = $(this);
            if (bloqueado) {
                $btn.prop('disabled', true).addClass('disabled');
                if (estadoPadron.mensaje) {
                    $btn.attr('title', estadoPadron.mensaje);
                }
            } else if (pendiente) {
                $btn.prop('disabled', true).addClass('disabled').removeAttr('title');
            } else {
                $btn.prop('disabled', false).removeClass('disabled').removeAttr('title');
            }
        });
    }

    window.clienteEstaHabilitadoParaFacturacion = function (estado) {
        var e = String(estado || '').toUpperCase();
        return e === '0' || e === 'R';
    };

    window.clienteEsRegularizadoParaOperacion = function (estado) {
        return esClienteRegularizado(estado);
    };

    window.formularioVentasBloqueadoPorPadron = function () {
        return $('#formgeneral').hasClass(CLASE_FORM_BLOQUEADO);
    };

    window.invalidarEstadoPadronOperacion = function () {
        estadoPadron.clienteId = null;
        estadoPadron.ok = null;
        estadoPadron.mensaje = null;
        estadoPadron.estadoCliente = null;
        aplicarBloqueoCargaVentas(false);
        actualizarAvisoPadronOperacion('', null);
        actualizarBotonesFacturaSegunPadron();
    };

    window.limpiarSeleccionClienteOperacion = function () {
        $('#cliente_id, .cliente_id').val('');
        $('#codigocliente, .codigocliente').val('');
        $('#nombrecliente, .nombrecliente').val('');
        window.invalidarEstadoPadronOperacion();
    };

    window.padronOperacionClienteOk = function (clienteId) {
        if (!requiereValidacionPadronSeleccion() && !requiereValidacionPadronPostCarga()) {
            return true;
        }

        var id = parseInt(clienteId, 10);
        if (!id) {
            return false;
        }

        if (esClienteRegularizado(estadoPadron.estadoCliente)) {
            return true;
        }

        return estadoPadron.clienteId === id && estadoPadron.ok === true;
    };

    window.validarPadronClienteOperacion = function (clienteId, opts) {
        opts = opts || {};

        return $.ajax({
            url: carpeta() + '/ventas/cliente/' + encodeURIComponent(clienteId) + '/validar-padron-operacion',
            method: 'POST',
            data: {
                _token: csrfToken(),
                condicioniva_id: opts.condicionivaId || '',
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
    };

    window.notificarBloqueoPadronCliente = function (message) {
        if (window.toastr) {
            toastr.error(message, 'Padr\u00f3n ARCA', { timeOut: 12000, closeButton: true, progressBar: true });
        } else {
            alert(message);
        }
    };

    window.ejecutarValidacionPadronOperacion = function (clienteId, opts) {
        opts = opts || {};
        var id = parseInt(clienteId, 10);
        var postCarga = opts.postCarga === true;
        var debeValidar = opts.forzar || requiereValidacionPadronSeleccion() || (postCarga && requiereValidacionPadronPostCarga());

        if (opts.estadoCliente) {
            estadoPadron.estadoCliente = opts.estadoCliente;
        }

        if (esClienteRegularizado(opts.estadoCliente || estadoPadron.estadoCliente)) {
            estadoPadron.clienteId = id || null;
            estadoPadron.ok = true;
            estadoPadron.mensaje = null;
            aplicarBloqueoCargaVentas(false);
            actualizarAvisoPadronOperacion('', null);
            actualizarBotonesFacturaSegunPadron();
            return $.Deferred().resolve({ ok: true, regularizado: true }).promise();
        }

        if ((!debeValidar) || !id) {
            estadoPadron.clienteId = id || null;
            estadoPadron.ok = true;
            estadoPadron.mensaje = null;
            aplicarBloqueoCargaVentas(false);
            actualizarBotonesFacturaSegunPadron();
            return $.Deferred().resolve({ ok: true, skipped: true }).promise();
        }

        if (!opts.forzar
            && estadoPadron.clienteId === id
            && estadoPadron.ok === true) {
            aplicarBloqueoCargaVentas(false);
            actualizarBotonesFacturaSegunPadron();
            return $.Deferred().resolve({ ok: true, cached: true }).promise();
        }

        if (!opts.forzar
            && estadoPadron.clienteId === id
            && estadoPadron.ok === false) {
            if (postCarga) {
                aplicarBloqueoCargaVentas(true, estadoPadron.mensaje);
            }
            actualizarBotonesFacturaSegunPadron();
            return $.Deferred().reject({ message: estadoPadron.mensaje }).promise();
        }

        if (postCarga) {
            actualizarAvisoPadronOperacion('Validando padr\u00f3n ARCA del cliente\u2026', 'pendiente');
        }

        actualizarBotonesFacturaSegunPadron();

        return window.validarPadronClienteOperacion(id, opts)
            .done(function () {
                estadoPadron.clienteId = id;
                estadoPadron.ok = true;
                estadoPadron.mensaje = null;
                aplicarBloqueoCargaVentas(false);
                actualizarAvisoPadronOperacion('', null);
                actualizarBotonesFacturaSegunPadron();
            })
            .fail(function (xhr) {
                var msg = mensajeDesdeXhr(xhr);
                estadoPadron.clienteId = id;
                estadoPadron.ok = false;
                estadoPadron.mensaje = msg;
                if (postCarga) {
                    aplicarBloqueoCargaVentas(true, msg);
                    window.notificarBloqueoPadronCliente(msg);
                }
                actualizarBotonesFacturaSegunPadron();
            });
    };

    /**
     * Tras cargar datos del cliente: valida ARCA en background sin bloquear la carga inicial.
     * Solo activo en pantallas con VALIDACION_PADRON_POST_CARGA (pedido / factura mostrador).
     */
    window.validarPadronClientePostCarga = function (clienteId, opts) {
        opts = opts || {};
        opts.postCarga = true;
        return window.ejecutarValidacionPadronOperacion(clienteId, opts);
    };

    window.ejecutarSiPadronOperacionOk = function (clienteId, accion, opts) {
        opts = opts || {};

        var id = parseInt(clienteId, 10);
        if (!id) {
            window.notificarBloqueoPadronCliente('Debe seleccionar un cliente.');
            return $.Deferred().reject({ message: 'Debe seleccionar un cliente.' }).promise();
        }

        opts.forzar = true;

        return window.ejecutarValidacionPadronOperacion(id, opts)
            .done(function () {
                if (typeof accion === 'function') {
                    accion();
                }
            })
            .fail(function (err) {
                var msg = (err && err.message) ? err.message : (estadoPadron.mensaje || mensajeDesdeXhr(err && err.xhr));
                window.notificarBloqueoPadronCliente(msg);
                if (typeof opts.onBloqueado === 'function') {
                    opts.onBloqueado(msg);
                }
            });
    };

    window.ejecutarAccionTrasValidarPadron = window.ejecutarSiPadronOperacionOk;

    window.verificarPadronClienteOperacion = function (clienteId, opts) {
        opts = opts || {};

        return window.ejecutarValidacionPadronOperacion(clienteId, opts).fail(function (err) {
            var msg = (err && err.message) ? err.message : mensajeDesdeXhr(err && err.xhr);
            window.notificarBloqueoPadronCliente(msg);
            if (typeof opts.onBloqueado === 'function') {
                opts.onBloqueado(msg);
            }
        });
    };

    window.validarPadronOperacionAntesSubmitForm = function (event) {
        if (!requiereValidacionPadronSeleccion() && !requiereValidacionPadronPostCarga()) {
            return true;
        }

        var form = event && event.target ? event.target : document.getElementById('formgeneral');
        if (!form) {
            return true;
        }

        if ($(form).data('padron-omitir-validacion')) {
            $(form).removeData('padron-omitir-validacion');
            return true;
        }

        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        var id = clienteIdDesdeFormulario();

        if (esClienteRegularizado(estadoPadron.estadoCliente)
            || esClienteRegularizado($('#estadocliente').val())) {
            $(form).data('padron-omitir-validacion', true).trigger('submit');
            return false;
        }

        if (estadoPadron.clienteId === id && estadoPadron.ok === false) {
            window.notificarBloqueoPadronCliente(estadoPadron.mensaje || 'Problemas en ARCA: no se puede operar con este cliente.');
            return false;
        }

        if (estadoPadron.clienteId === id && estadoPadron.ok === true) {
            $(form).data('padron-omitir-validacion', true).trigger('submit');
            return false;
        }

        window.ejecutarValidacionPadronOperacion(id, {
            condicionivaId: $('#condicioniva_id').val() || '',
            estadoCliente: $('#estadocliente').val() || estadoPadron.estadoCliente,
        }).done(function () {
            $(form).data('padron-omitir-validacion', true).trigger('submit');
        }).fail(function (err) {
            var msg = (err && err.message) ? err.message : (estadoPadron.mensaje || 'Problemas en ARCA: no se puede operar con este cliente.');
            window.notificarBloqueoPadronCliente(msg);
        });

        return false;
    };

    window.inicializarPadronOperacionDesdeFormulario = function () {
        if (!requiereValidacionPadronSeleccion() && !requiereValidacionPadronPostCarga()) {
            return;
        }

        var id = clienteIdDesdeFormulario();
        var estadoCliente = $('#estadocliente').val() || null;

        if (id > 0) {
            window.validarPadronClientePostCarga(id, {
                condicionivaId: $('#condicioniva_id').val() || '',
                estadoCliente: estadoCliente,
            });
        } else {
            actualizarBotonesFacturaSegunPadron();
        }
    };

    $(function () {
        if (!requiereValidacionPadronSeleccion() && !requiereValidacionPadronPostCarga()) {
            return;
        }

        $(document).on('change', '#cliente_id, #codigocliente', function () {
            window.invalidarEstadoPadronOperacion();
        });

        window.inicializarPadronOperacionDesdeFormulario();
    });
}(window, jQuery));
