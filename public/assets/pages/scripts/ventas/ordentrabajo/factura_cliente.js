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
        // Si cambió la razón social, no conservar el lugar del pedido anterior.
        if (clienteOrigenId > 0 && (parseInt(data.id, 10) || 0) !== clienteOrigenId) {
            window.limpiarLugarEntregaFacturaOt();
        }
        $(document).trigger('clienteFacturaOtElegido', [data]);
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

    function etiquetaLugarEntregaOt(entrega) {
        if (!entrega) {
            return '';
        }
        if (entrega.etiqueta) {
            return String(entrega.etiqueta);
        }
        var nombre = String(entrega.nombre || '').trim();
        if (nombre) {
            return nombre;
        }
        var domicilio = String(entrega.domicilio || '').trim();
        if (domicilio) {
            return domicilio;
        }
        return String(entrega.localidad || '').trim();
    }

    function entregasNombradasOt(entregas) {
        return (entregas || []).filter(function (entrega) {
            return String(entrega.nombre || '').trim() !== '' || entrega.nombre_usable === true;
        });
    }

    window.prefijarLugarEntregaFacturaOt = function (clienteEntregaId, lugarentrega) {
        $('#factura_ot_cliente_entrega_id_previa').val(clienteEntregaId || '');
        $('#factura_ot_lugarentrega').val(lugarentrega || '');
    };

    window.datosLugarEntregaFacturaOt = function () {
        return {
            cliente_entrega_id: String($('#factura_ot_cliente_entrega_id').val() || '').trim(),
            lugarentrega: String($('#factura_ot_lugarentrega').val() || '').trim()
        };
    };

    window.hidratarLugarEntregaFacturaOt = function (clienteId) {
        var $sel = $('#factura_ot_cliente_entrega_id');
        var $txt = $('#factura_ot_lugarentrega');
        var $aviso = $('#aviso-lugarentrega-factura-ot');
        var $label = $('#label-factura-ot-lugarentrega');
        var idActual = $sel.val() || $('#factura_ot_cliente_entrega_id_previa').val() || '';
        var textoActual = String($txt.val() || '').trim();

        $aviso.text('');
        $label.removeClass('requerido');

        if (!$sel.length || !clienteId) {
            if ($sel.length) {
                $sel.hide().empty();
            }
            if ($txt.length) {
                $txt.prop('readonly', false).val(textoActual);
            }
            return;
        }

        $.get(carpetaBase + '/ventas/leercliente_entrega/' + clienteId, function (data) {
            var entr = $.map(data, function (value) { return [value]; });
            var nombradas = entregasNombradasOt(entr);

            $sel.empty().append($('<option></option>').val('').text('-- Seleccionar --'));
            $.each(entr, function (_, entrega) {
                $sel.append($('<option></option>').val(entrega.id).text(etiquetaLugarEntregaOt(entrega)));
            });

            if (entr.length) {
                $sel.show();
                if (idActual && $sel.find('option[value="' + idActual + '"]').length) {
                    $sel.val(idActual);
                } else if (entr.length === 1) {
                    $sel.val(entr[0].id);
                }
                if ($sel.val()) {
                    var elegida = null;
                    $.each(entr, function (_, entrega) {
                        if (String(entrega.id) === String($sel.val())) {
                            elegida = entrega;
                        }
                    });
                    $txt.val(etiquetaLugarEntregaOt(elegida) || textoActual);
                    $('#factura_ot_cliente_entrega_id_previa').val($sel.val());
                } else {
                    $txt.val(textoActual);
                }
                $txt.prop('readonly', nombradas.length > 0);
                if (nombradas.length) {
                    $label.addClass('requerido');
                    if (!$sel.val() && !String($txt.val() || '').trim()) {
                        $aviso.text('Este cliente tiene lugares de entrega. Elija uno para facturar.');
                    }
                } else {
                    $aviso.text('El lugar del ABM no tiene nombre. Puede completarlo aquí o cargarlo en el cliente.');
                }
            } else {
                $sel.hide().val('');
                $txt.prop('readonly', false).val(textoActual);
                $aviso.text('El cliente no tiene lugares de entrega en el ABM. Puede cargarlo aquí; conviene cargarlo también en el cliente.');
            }
        });
    };

    window.validarLugarEntregaFacturaOt = function () {
        if (!$('#factura_ot_lugarentrega').length) {
            return true;
        }
        var datos = window.datosLugarEntregaFacturaOt();
        var obligatorio = $('#label-factura-ot-lugarentrega').hasClass('requerido');
        if (obligatorio && !datos.cliente_entrega_id && !datos.lugarentrega) {
            aviso('Debe seleccionar un lugar de entrega del cliente');
            $('#factura_ot_lugarentrega').trigger('focus');
            return false;
        }
        return true;
    };

    window.limpiarLugarEntregaFacturaOt = function () {
        $('#factura_ot_cliente_entrega_id').empty().hide().val('');
        $('#factura_ot_cliente_entrega_id_previa').val('');
        $('#factura_ot_lugarentrega').val('').prop('readonly', false);
        $('#aviso-lugarentrega-factura-ot').text('');
        $('#label-factura-ot-lugarentrega').removeClass('requerido');
    };

    $(function () {
        if (!$('#factura_ot_codigocliente').length) {
            return;
        }

        $(document).on('change', '#factura_ot_cliente_entrega_id', function () {
            var id = String($(this).val() || '');
            var etiqueta = '';
            if (id) {
                etiqueta = String($(this).find('option:selected').text() || '').trim();
                if (etiqueta === '-- Seleccionar --') {
                    etiqueta = '';
                }
            }
            $('#factura_ot_lugarentrega').val(etiqueta);
            $('#factura_ot_cliente_entrega_id_previa').val(id);
            if (id || etiqueta) {
                $('#aviso-lugarentrega-factura-ot').text('');
            }
        });

        $(document).on('clienteFacturaOtElegido', function (_event, data) {
            var clienteId = data && data.id ? data.id : window.clienteIdFacturaOt();
            if (typeof window.hidratarLugarEntregaFacturaOt === 'function') {
                window.hidratarLugarEntregaFacturaOt(clienteId);
            }
        });

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
