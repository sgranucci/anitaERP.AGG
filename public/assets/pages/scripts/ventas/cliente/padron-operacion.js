(function (window, $) {
    'use strict';

    var estadoPadron = {
        clienteId: null,
        ok: null,
        mensaje: null,
    };

    var SELECTOR_BOTONES_FACTURA = '[data-padron-accion-factura]';

    function requiereValidacionPadron() {
        return window.REQUIERE_VALIDACION_PADRON_OPERACION === true;
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

    function actualizarBotonesFacturaSegunPadron() {
        if (!requiereValidacionPadron()) {
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

    window.invalidarEstadoPadronOperacion = function () {
        estadoPadron.clienteId = null;
        estadoPadron.ok = null;
        estadoPadron.mensaje = null;
        actualizarBotonesFacturaSegunPadron();
    };

    window.limpiarSeleccionClienteOperacion = function () {
        $('#cliente_id, .cliente_id').val('');
        $('#codigocliente, .codigocliente').val('');
        $('#nombrecliente, .nombrecliente').val('');
        window.invalidarEstadoPadronOperacion();
    };

    window.padronOperacionClienteOk = function (clienteId) {
        if (!requiereValidacionPadron()) {
            return true;
        }

        var id = parseInt(clienteId, 10);
        if (!id) {
            return false;
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

        if (!requiereValidacionPadron() || !id) {
            estadoPadron.clienteId = id || null;
            estadoPadron.ok = true;
            estadoPadron.mensaje = null;
            actualizarBotonesFacturaSegunPadron();

            return $.Deferred().resolve({ ok: true, skipped: true }).promise();
        }

        if (!opts.forzar
            && estadoPadron.clienteId === id
            && estadoPadron.ok === true) {
            actualizarBotonesFacturaSegunPadron();

            return $.Deferred().resolve({ ok: true, cached: true }).promise();
        }

        if (!opts.forzar
            && estadoPadron.clienteId === id
            && estadoPadron.ok === false) {
            actualizarBotonesFacturaSegunPadron();

            return $.Deferred().reject({ message: estadoPadron.mensaje }).promise();
        }

        actualizarBotonesFacturaSegunPadron();

        return window.validarPadronClienteOperacion(id, opts)
            .done(function () {
                estadoPadron.clienteId = id;
                estadoPadron.ok = true;
                estadoPadron.mensaje = null;
                actualizarBotonesFacturaSegunPadron();
            })
            .fail(function (xhr) {
                var msg = mensajeDesdeXhr(xhr);
                estadoPadron.clienteId = id;
                estadoPadron.ok = false;
                estadoPadron.mensaje = msg;
                actualizarBotonesFacturaSegunPadron();
            });
    };

    /**
     * Ejecuta acción de facturación usando cache de padrón (sin reconsultar ARCA salvo cache miss).
     */
    window.ejecutarSiPadronOperacionOk = function (clienteId, accion, opts) {
        opts = opts || {};

        if (!requiereValidacionPadron()) {
            if (typeof accion === 'function') {
                accion();
            }

            return $.Deferred().resolve({ ok: true, skipped: true }).promise();
        }

        var id = parseInt(clienteId, 10);
        if (!id) {
            window.notificarBloqueoPadronCliente('Debe seleccionar un cliente.');
            return $.Deferred().reject({ message: 'Debe seleccionar un cliente.' }).promise();
        }

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
        if (!requiereValidacionPadron()) {
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
        }).done(function () {
            $(form).data('padron-omitir-validacion', true).trigger('submit');
        }).fail(function (err) {
            var msg = (err && err.message) ? err.message : (estadoPadron.mensaje || 'Problemas en ARCA: no se puede operar con este cliente.');
            window.notificarBloqueoPadronCliente(msg);
        });

        return false;
    };

    window.inicializarPadronOperacionDesdeFormulario = function () {
        if (!requiereValidacionPadron()) {
            return;
        }

        var id = clienteIdDesdeFormulario();
        if (id > 0) {
            window.ejecutarValidacionPadronOperacion(id, {
                condicionivaId: $('#condicioniva_id').val() || '',
            });
        } else {
            actualizarBotonesFacturaSegunPadron();
        }
    };

    $(function () {
        if (!requiereValidacionPadron()) {
            return;
        }

        $(document).on('change', '#cliente_id, #codigocliente', function () {
            window.invalidarEstadoPadronOperacion();
        });

        window.inicializarPadronOperacionDesdeFormulario();
    });
}(window, jQuery));
