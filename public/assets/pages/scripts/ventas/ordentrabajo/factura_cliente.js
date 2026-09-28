/**
 * Cliente de la sesión de factura Ferli (pedido por líneas tildadas u OT).
 * Confirma el cliente del documento o lo cambia. No pisa el cliente del pedido.
 */
(function (window, $) {
    var contextoPrev = null;
    var onElegidoPrev;
    var clienteOrigenId = 0;

    function aviso(mensaje) {
        window.alert(mensaje);
    }

    function habilitarGenerar(listo) {
        $('#aceptaFacturarOrdenTrabajoModal').prop('disabled', !listo);
    }

    function pintar(data) {
        $('#factura_ot_cliente_id').val(data.id);
        $('#factura_ot_codigocliente').val(data.codigo || '');
        $('#factura_ot_nombrecliente').val(data.nombre || '');
        $('#nombrecliente').val(data.nombre || '');
    }

    function aplicaDescuentoSiCambia(data) {
        var id = parseInt(data.id, 10) || 0;
        if (clienteOrigenId > 0 && id !== clienteOrigenId && data.descuento != null) {
            $('#descuentopie').val(data.descuento);
        }
    }

    function clienteFacturable(data, avisar) {
        if (!data || !data.id) {
            if (avisar) {
                aviso('Cliente inexistente');
            }
            return false;
        }
        if (String(data.id) === String(window.CLIENTE_STOCK_ID || '')) {
            if (avisar) {
                aviso('No puede facturar cliente STOCK');
            }
            return false;
        }
        var politica = data.politica_comercial || null;
        if (window.clientePoliticaComercial
            && !window.clientePoliticaComercial.permiteOperacion('factura', politica)) {
            if (avisar) {
                aviso(window.clientePoliticaComercial.mensaje('factura', politica));
            }
            return false;
        }
        if (data.numerodocumento == null || String(data.numerodocumento).trim() === '') {
            if (avisar) {
                aviso('El cliente no tiene CUIT');
            }
            return false;
        }
        return true;
    }

    function aplicarCliente(data, avisar) {
        if (!clienteFacturable(data, avisar)) {
            $('#factura_ot_cliente_id').val('');
            $('#factura_ot_nombrecliente').val('');
            habilitarGenerar(false);
            return false;
        }
        pintar(data);
        aplicaDescuentoSiCambia(data);
        habilitarGenerar(true);
        return true;
    }

    function leerCliente(url, avisar) {
        habilitarGenerar(false);
        return $.get(url).done(function (data) {
            if (!aplicarCliente(data, avisar)) {
                $('#factura_ot_codigocliente').trigger('focus');
            }
        }).fail(function () {
            if (avisar) {
                aviso('Cliente inexistente');
            }
            $('#factura_ot_cliente_id').val('');
            habilitarGenerar(false);
        });
    }

    function restaurarConsulta() {
        if (contextoPrev !== null) {
            window.CLIENTE_CONSULTA_CONTEXTO = contextoPrev;
            contextoPrev = null;
        }
        window.onClienteElegidoEnConsulta = onElegidoPrev;
        onElegidoPrev = undefined;
        if ($('#facturarOrdenTrabajoModal').hasClass('show')) {
            $('body').addClass('modal-open');
            if ($('.modal-backdrop').length === 0) {
                $('<div class="modal-backdrop fade show"></div>').appendTo(document.body);
            }
        }
    }

    function abrirConsulta() {
        if (!$('#consultaclienteModal').length) {
            aviso('No está disponible la consulta de clientes.');
            return;
        }
        contextoPrev = window.CLIENTE_CONSULTA_CONTEXTO;
        window.CLIENTE_CONSULTA_CONTEXTO = 'factura';
        onElegidoPrev = window.onClienteElegidoEnConsulta;
        window.onClienteElegidoEnConsulta = function (fila) {
            if (fila && fila.id) {
                leerCliente(carpetaBase + '/ventas/leeruncliente/' + fila.id, true);
            }
            return true;
        };
        if (typeof consultaClienteModalAbriendo !== 'undefined') {
            consultaClienteModalAbriendo = true;
        }
        $('#consultaclienteModal').data('gastroConsultaDestino', 'factura');
        $('#consultaclienteModal').modal('show');
    }

    window.prefijarClienteFacturaOt = function (clienteId) {
        clienteOrigenId = parseInt(clienteId, 10) || 0;
        $('#factura_ot_codigocliente').val('');
        $('#factura_ot_nombrecliente').val('');
        $('#factura_ot_cliente_id').val('');
        if (clienteOrigenId <= 0) {
            habilitarGenerar(false);
            return;
        }
        leerCliente(carpetaBase + '/ventas/leeruncliente/' + clienteOrigenId, false);
    };

    window.clienteIdFacturaOt = function () {
        return String($('#factura_ot_cliente_id').val() || '').trim();
    };

    window.validarClienteFacturaOt = function () {
        var id = window.clienteIdFacturaOt();
        var codigo = String($('#factura_ot_codigocliente').val() || '').trim();
        if (!id || codigo === '') {
            aviso('Confirme el cliente de la factura (código y Enter, o F1).');
            $('#factura_ot_codigocliente').trigger('focus');
            return false;
        }
        return true;
    };

    $(function () {
        if (!$('#factura_ot_codigocliente').length) {
            return;
        }

        $('#factura-ot-consultacliente').on('click', function (event) {
            event.preventDefault();
            abrirConsulta();
        });

        $('#factura_ot_codigocliente').on('keydown', function (event) {
            if (event.key === 'F1' || event.keyCode === 112) {
                event.preventDefault();
                event.stopPropagation();
                abrirConsulta();
                return;
            }
            if (event.key === 'Enter' || event.keyCode === 13) {
                event.preventDefault();
                event.stopPropagation();
                var codigo = String($(this).val() || '').trim();
                if (codigo === '') {
                    $('#factura_ot_cliente_id').val('');
                    $('#factura_ot_nombrecliente').val('');
                    habilitarGenerar(false);
                    return;
                }
                leerCliente(carpetaBase + '/ventas/leerunclienteporcodigo/' + encodeURIComponent(codigo), true);
            }
        });

        $('#factura_ot_codigocliente').on('input', function () {
            $('#factura_ot_cliente_id').val('');
            habilitarGenerar(false);
        });

        $('#consultaclienteModal')
            .on('hidden.bs.modal.facturaOtCliente', restaurarConsulta)
            .on('shown.bs.modal.facturaOtCliente', function () {
                if ($('.modal.show').length < 2) {
                    return;
                }
                var z = 1060 + (20 * $('.modal.show').length);
                $(this).css('z-index', z);
                window.setTimeout(function () {
                    $('.modal-backdrop').last().css('z-index', z - 10);
                }, 0);
            });
    });
}(window, jQuery));
