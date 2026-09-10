var abriendoModalFacturaReferencia = false;
var facturaReferenciaInvalidaMarcada = false;
var buscaFacturaReferenciaTimer = null;

function esTeclaF1FacturaReferencia(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaFacturaReferenciaAbierto() {
    var $m = $('#consultafacturareferenciaModal');
    return $m.length && ($m.hasClass('show') || abriendoModalFacturaReferencia);
}

function parsearHtmlConsultaFacturaReferencia(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function clienteIdFacturaReferencia() {
    return parseInt($('#cliente_id').val() || '0', 10) || 0;
}

function empresaIdFacturaReferencia() {
    return parseInt($('#empresa_id').val() || '0', 10) || 0;
}

function preferirSoloFceReferencia() {
    var $wrap = $('#fce-nc-mostrador-wrap');
    if ($wrap.length && String($wrap.attr('data-nc-origen-fce') || '0') === '1') {
        return true;
    }
    var abr = String($('#tipotransaccion_id option:selected').attr('data-abreviatura') || '').toUpperCase();
    return abr.indexOf('FCE') >= 0 || abr.indexOf('NCE') >= 0;
}

function buscarDatosFacturaReferencia(consulta) {
    var clienteId = clienteIdFacturaReferencia();
    if (clienteId <= 0) {
        $('#datos-factura-referencia').html('<tr><td colspan="5" class="text-muted">Seleccione un cliente primero.</td></tr>');
        return;
    }
    $.ajax({
        url: carpetaBase + '/ventas/factura/consulta-referencia',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || '',
            cliente_id: clienteId,
            empresa_id: empresaIdFacturaReferencia(),
            solo_fce: $('#consulta-factura-referencia-solo-fce').is(':checked') ? 1 : 0
        }
    })
        .done(function (respuesta) {
            $('#datos-factura-referencia').html(parsearHtmlConsultaFacturaReferencia(respuesta));
        })
        .fail(function () {
            $('#datos-factura-referencia').html('<tr><td colspan="5">Error al consultar comprobantes</td></tr>');
        });
}

function aplicarFacturaReferencia(codigo) {
    facturaReferenciaInvalidaMarcada = false;
    $('#fce_comprobante_referenciado')
        .removeAttr('data-factura-referencia-invalida')
        .val($.trim(codigo || ''));
    if (typeof window.actualizarFceNcMostrador === 'function') {
        window.actualizarFceNcMostrador();
    }
    $('#fce_anulacion').focus();
}

function abrirModalFacturaReferencia() {
    var clienteId = clienteIdFacturaReferencia();
    if (clienteId <= 0) {
        alert('Seleccione el cliente antes de consultar el comprobante referenciado.');
        $('#codigocliente').focus();
        return;
    }
    abriendoModalFacturaReferencia = true;
    var nombre = $.trim($('#nombrecliente').val() || '');
    var codigoCli = $.trim($('#codigocliente').val() || '');
    $('#consulta-factura-referencia-cliente').text(
        'Cliente: ' + (codigoCli ? codigoCli + ' — ' : '') + (nombre || ('#' + clienteId))
    );
    $('#consulta-factura-referencia-solo-fce').prop('checked', preferirSoloFceReferencia());
    $('#consulta-factura-referencia').val('');
    buscarDatosFacturaReferencia('');
    var $modal = $('#consultafacturareferenciaModal');
    $modal.one('shown.bs.modal', function () {
        abriendoModalFacturaReferencia = false;
        $('#consulta-factura-referencia').focus();
    });
    $modal.one('hidden.bs.modal', function () {
        abriendoModalFacturaReferencia = false;
    });
    $modal.modal('show');
}

function resolverFacturaReferenciaPorCodigo(avisar) {
    if (modalConsultaFacturaReferenciaAbierto()) {
        return;
    }
    var codigo = $.trim($('#fce_comprobante_referenciado').val() || '');
    if (codigo === '') {
        facturaReferenciaInvalidaMarcada = false;
        $('#fce_comprobante_referenciado').removeAttr('data-factura-referencia-invalida');
        return;
    }
    if ($('#fce_comprobante_referenciado').attr('data-factura-referencia-invalida') === codigo) {
        return;
    }
    var clienteId = clienteIdFacturaReferencia();
    if (clienteId <= 0) {
        if (avisar) {
            alert('Seleccione el cliente antes de indicar el comprobante referenciado.');
            $('#codigocliente').focus();
        }
        return;
    }
    $.ajax({
        url: carpetaBase + '/ventas/factura/resolver-referencia',
        type: 'GET',
        dataType: 'json',
        data: {
            valor: codigo,
            cliente_id: clienteId,
            empresa_id: empresaIdFacturaReferencia()
        }
    })
        .done(function (resp) {
            if (resp && resp.ok && resp.item && resp.item.codigo) {
                aplicarFacturaReferencia(resp.item.codigo);
                return;
            }
            if (avisar) {
                var msg = (resp && resp.mensaje) || 'Comprobante no encontrado para el cliente.';
                if ($('#consultafacturareferenciaModal').hasClass('show')) {
                    $('#consultafacturareferenciaModal').modal('hide');
                }
                setTimeout(function () {
                    alert(msg);
                    $('#fce_comprobante_referenciado').focus();
                }, 0);
            }
            facturaReferenciaInvalidaMarcada = true;
            $('#fce_comprobante_referenciado').attr('data-factura-referencia-invalida', codigo);
        })
        .fail(function () {
            if (avisar) {
                setTimeout(function () {
                    alert('Error al resolver el comprobante referenciado.');
                    $('#fce_comprobante_referenciado').focus();
                }, 0);
            }
        });
}

function activa_eventos_consultafacturareferencia() {
    $(document).off('click.fceRef', '.consultafacturareferencia').on('click.fceRef', '.consultafacturareferencia', function (e) {
        e.preventDefault();
        abrirModalFacturaReferencia();
    });

    $(document).off('keydown.fceRef', '#fce_comprobante_referenciado').on('keydown.fceRef', '#fce_comprobante_referenciado', function (e) {
        if (esTeclaF1FacturaReferencia(e)) {
            e.preventDefault();
            abrirModalFacturaReferencia();
            return;
        }
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            resolverFacturaReferenciaPorCodigo(true);
        }
    });

    $(document).off('input.fceRef', '#fce_comprobante_referenciado').on('input.fceRef', '#fce_comprobante_referenciado', function () {
        $(this).removeAttr('data-factura-referencia-invalida');
        facturaReferenciaInvalidaMarcada = false;
    });

    $(document).off('blur.fceRef', '#fce_comprobante_referenciado').on('blur.fceRef', '#fce_comprobante_referenciado', function () {
        if (modalConsultaFacturaReferenciaAbierto()) {
            return;
        }
        resolverFacturaReferenciaPorCodigo(false);
    });

    $(document).off('input.fceRefBuscar', '#consulta-factura-referencia').on('input.fceRefBuscar', '#consulta-factura-referencia', function () {
        var v = $(this).val() || '';
        clearTimeout(buscaFacturaReferenciaTimer);
        buscaFacturaReferenciaTimer = setTimeout(function () {
            buscarDatosFacturaReferencia(v);
        }, 250);
    });

    $(document).off('keydown.fceRefBuscar', '#consulta-factura-referencia').on('keydown.fceRefBuscar', '#consulta-factura-referencia', function (e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) {
            return;
        }
        e.preventDefault();
        var $first = $('#datos-factura-referencia .eligeconsultafacturareferencia').first();
        if ($first.length) {
            $first.trigger('click');
        }
    });

    $(document).off('change.fceRefSoloFce', '#consulta-factura-referencia-solo-fce').on('change.fceRefSoloFce', '#consulta-factura-referencia-solo-fce', function () {
        buscarDatosFacturaReferencia($('#consulta-factura-referencia').val() || '');
    });

    $(document).off('click.fceRefElige', '.eligeconsultafacturareferencia').on('click.fceRefElige', '.eligeconsultafacturareferencia', function (e) {
        e.preventDefault();
        var $tr = $(this).closest('tr');
        var codigo = $.trim($tr.find('.venta_codigo').first().text());
        if ($('#consultafacturareferenciaModal').hasClass('show')) {
            $('#consultafacturareferenciaModal').modal('hide');
        }
        setTimeout(function () {
            aplicarFacturaReferencia(codigo);
        }, 0);
    });

    $(document).off('change.fceRefCliente', '#cliente_id').on('change.fceRefCliente', '#cliente_id', function () {
        var $wrap = $('#fce-nc-mostrador-wrap');
        if (!$wrap.length || $wrap.hasClass('d-none')) {
            return;
        }
        var origenFce = String($wrap.attr('data-nc-origen-fce') || '0') === '1';
        if (origenFce) {
            return;
        }
        $('#fce_comprobante_referenciado').val('').removeAttr('data-factura-referencia-invalida');
        if (typeof window.actualizarFceNcMostrador === 'function') {
            window.actualizarFceNcMostrador();
        }
    });
}

$(function () {
    if ($('#fce-nc-mostrador-wrap').length || $('.consultafacturareferencia').length) {
        activa_eventos_consultafacturareferencia();
    }
});
