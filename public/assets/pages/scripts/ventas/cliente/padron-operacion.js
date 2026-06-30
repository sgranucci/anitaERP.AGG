(function (window, $) {
    'use strict';

    var estadoPadron = {
        clienteId: null,
        ok: null,
        mensaje: null,
    };

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

    window.clienteEstaHabilitadoParaFacturacion = function (estado) {
        var e = String(estado || '').toUpperCase();
        return e === '0' || e === 'R';
    };

    window.invalidarEstadoPadronOperacion = function () {
        estadoPadron.clienteId = null;
        estadoPadron.ok = null;
        estadoPadron.mensaje = null;
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

            return $.Deferred().resolve({ ok: true, skipped: true }).promise();
        }

        if (!opts.forzar
            && estadoPadron.clienteId === id
            && estadoPadron.ok === true) {
            return $.Deferred().resolve({ ok: true, cached: true }).promise();
        }

        if (!opts.forzar
            && estadoPadron.clienteId === id
            && estadoPadron.ok === false) {
            return $.Deferred().reject({ message: estadoPadron.mensaje }).promise();
        }

        return window.validarPadronClienteOperacion(id, opts)
            .done(function () {
                estadoPadron.clienteId = id;
                estadoPadron.ok = true;
                estadoPadron.mensaje = null;
            })
            .fail(function (xhr) {
                var msg = mensajeDesdeXhr(xhr);
                estadoPadron.clienteId = id;
                estadoPadron.ok = false;
                estadoPadron.mensaje = msg;
            });
    };

    window.ejecutarAccionTrasValidarPadron = function (clienteId, accion, opts) {
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

        window.ejecutarAccionTrasValidarPadron(clienteIdDesdeFormulario(), function () {
            $(form).data('padron-omitir-validacion', true).trigger('submit');
        }, { forzar: true });

        return false;
    };

    $(function () {
        if (!requiereValidacionPadron()) {
            return;
        }

        $(document).on('change', '#cliente_id, #codigocliente', function () {
            window.invalidarEstadoPadronOperacion();
        });
    });
}(window, jQuery));
