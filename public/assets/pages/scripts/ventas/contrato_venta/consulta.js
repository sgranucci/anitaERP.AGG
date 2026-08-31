var ptrContratoVentaId = $();
var ptrCodigoContratoVenta = $();
var ptrNombreContratoVenta = $();
var ptrFilaContratoVenta = $();
var contratoVentaInvalidoMarcado = false;
var abriendoModalContratoVenta = false;

function esTeclaF1ContratoVenta(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaContratoVentaAbierto() {
    var $m = $('#consultacontratoventaModal');
    return $m.length && ($m.hasClass('show') || abriendoModalContratoVenta);
}

function parsearHtmlConsultaContratoVenta(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function buscar_datos_contrato_venta(consulta) {
    var empresaId = $('#empresa_id').val() || '';
    $.ajax({
        url: carpetaBase + '/ventas/contrato-venta/consulta',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || '',
            empresa_id: empresaId
        }
    })
        .done(function (respuesta) {
            $('#datoscontratoventa').html(parsearHtmlConsultaContratoVenta(respuesta));
        })
        .fail(function () {
            console.log('error consulta contrato venta');
        });
}

function actualizarLinkEditarContratoVenta($ctx, contratoId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-contrato-venta');
    if (!$link.length) {
        return;
    }
    var id = parseInt(contratoId, 10) || 0;
    if (id > 0) {
        $link
            .attr('href', carpetaBase + '/ventas/contrato-venta/' + id + '/editar?origen=modal_consulta&vista=consulta')
            .removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function dataContratoDesdeFila($row) {
    return {
        id: $.trim($row.find('.contrato_venta_id').text()),
        codigo: $.trim($row.find('.codigocontratoventa').text()),
        cliente: $.trim($row.find('.clientecontratoventa').text()),
        concepto: $.trim($row.find('.conceptocontratoventa').text()),
        estado: $.trim($row.find('.estadocontratoventa').text()),
        empresa: $.trim($row.find('.empresacontratoventa').text())
    };
}

function aplicarContratoVentaEnCampo($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.contrato_venta_id').val(data.id);
    $ctx.find('.codigocontratoventa').val(data.codigo);
    $ctx.find('.nombrecontratoventa').val(data.cliente || data.concepto || data.codigo);
    actualizarLinkEditarContratoVenta($ctx, data.id);
    $(document).trigger('contratoVentaElegido', [data]);
}

function limpiarContratoVentaEnCampo($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.contrato_venta_id').val('');
    $ctx.find('.codigocontratoventa').val('');
    $ctx.find('.nombrecontratoventa').val('');
    actualizarLinkEditarContratoVenta($ctx, 0);
}

function resolverContratoVentaPorCodigo($ctx, codigo, avisar) {
    codigo = $.trim(codigo || '');
    if (!codigo) {
        limpiarContratoVentaEnCampo($ctx);
        return;
    }
    var empresaId = $('#empresa_id').val() || '';
    $.ajax({
        url: carpetaBase + '/ventas/contrato-venta/por-codigo/' + encodeURIComponent(codigo),
        type: 'GET',
        dataType: 'json',
        data: { empresa_id: empresaId }
    }).done(function (resp) {
        if (!resp || !resp.ok) {
            limpiarContratoVentaEnCampo($ctx);
            $ctx.find('.codigocontratoventa').val(codigo).data('cv-invalido', '1');
            if (avisar) {
                if (typeof window.liberarPantallaModalesBloqueados === 'function') {
                    window.liberarPantallaModalesBloqueados();
                }
                setTimeout(function () {
                    alert('Abono / contrato no encontrado o inactivo: ' + codigo);
                    $ctx.find('.codigocontratoventa').focus().select();
                }, 0);
            }
            return;
        }
        aplicarContratoVentaEnCampo($ctx, {
            id: resp.id || resp.contrato_venta_id,
            codigo: resp.codigo_contrato || codigo,
            cliente: resp.cliente_nombre || '',
            concepto: resp.codigo || '',
            estado: 'activo',
            empresa: ''
        });
        $ctx.find('.codigocontratoventa').removeData('cv-invalido');
    });
}

function abrirModalConsultaContratoVenta($campo) {
    var $ctx = $campo.closest('.tm-contrato-venta-campo');
    ptrFilaContratoVenta = $ctx;
    ptrContratoVentaId = $ctx.find('.contrato_venta_id');
    ptrCodigoContratoVenta = $ctx.find('.codigocontratoventa');
    ptrNombreContratoVenta = $ctx.find('.nombrecontratoventa');
    abriendoModalContratoVenta = true;
    $('#consultacontratoventa').val('');
    buscar_datos_contrato_venta('');
    $('#consultacontratoventaModal').modal('show');
}

function activa_eventos_consultacontratoventa() {
    $(document).off('click.cvContrato', '.consultacontratoventa').on('click.cvContrato', '.consultacontratoventa', function (e) {
        e.preventDefault();
        abrirModalConsultaContratoVenta($(this));
    });

    $(document).off('keydown.cvContratoF1', '.tm-contrato-venta-campo .codigocontratoventa')
        .on('keydown.cvContratoF1', '.tm-contrato-venta-campo .codigocontratoventa', function (e) {
            if (esTeclaF1ContratoVenta(e)) {
                e.preventDefault();
                abrirModalConsultaContratoVenta($(this));
            }
        });

    $(document).off('keydown.cvContratoEnter', '.tm-contrato-venta-campo .codigocontratoventa')
        .on('keydown.cvContratoEnter', '.tm-contrato-venta-campo .codigocontratoventa', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                resolverContratoVentaPorCodigo($(this).closest('.tm-contrato-venta-campo'), $(this).val(), true);
            }
        });

    $(document).off('blur.cvContrato', '.tm-contrato-venta-campo .codigocontratoventa')
        .on('blur.cvContrato', '.tm-contrato-venta-campo .codigocontratoventa', function () {
            if (modalConsultaContratoVentaAbierto()) {
                return;
            }
            var $ctx = $(this).closest('.tm-contrato-venta-campo');
            var codigo = $.trim($(this).val() || '');
            if (!codigo) {
                limpiarContratoVentaEnCampo($ctx);
                return;
            }
            if ($(this).data('cv-invalido') === '1') {
                return;
            }
            resolverContratoVentaPorCodigo($ctx, codigo, false);
        });

    $(document).off('input.cvContrato', '.tm-contrato-venta-campo .codigocontratoventa')
        .on('input.cvContrato', '.tm-contrato-venta-campo .codigocontratoventa', function () {
            $(this).removeData('cv-invalido');
        });

    $(document).off('keyup.cvContratoBuscar', '#consultacontratoventa')
        .on('keyup.cvContratoBuscar', '#consultacontratoventa', function () {
            buscar_datos_contrato_venta($(this).val());
        });

    $(document).off('keydown.cvContratoBuscarEnter', '#consultacontratoventa')
        .on('keydown.cvContratoBuscarEnter', '#consultacontratoventa', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                var $first = $('#datoscontratoventa tr').first();
                if ($first.length) {
                    $first.find('.eligeconsultacontratoventa').trigger('click');
                }
            }
        });

    $(document).off('click.cvContratoElige', '.eligeconsultacontratoventa')
        .on('click.cvContratoElige', '.eligeconsultacontratoventa', function () {
            var data = dataContratoDesdeFila($(this).closest('tr'));
            var $filaFactura = $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').filter(function () {
                return $.trim($(this).find('.codigoarticulo').val() || '') === ''
                    && $.trim($(this).find('.concepto_venta_id').val() || '') === '';
            }).first();
            if (!$filaFactura.length) {
                $filaFactura = $('#tbody-tabla tr.item-factura, #tbody-tabla tr.item-pedido').last();
            }
            if ($filaFactura.length && typeof window.aplicarContratoVentaPrefillEnFila === 'function') {
                var fecha = $.trim($('#fechafactura').val() || '') || '';
                $.get(carpetaBase + '/ventas/contrato-venta/prefill', {
                    contrato_id: data.id,
                    fecha: fecha
                }).done(function (resp) {
                    if (resp && (resp.ok || resp.contrato_venta_id)) {
                        var prefill = resp.linea || resp;
                        window.aplicarContratoVentaPrefillEnFila($filaFactura, prefill);
                    } else {
                        alert((resp && resp.error) || 'No se pudo prellenar desde el abono.');
                    }
                }).fail(function () {
                    alert('Error al prellenar desde el abono.');
                });
            } else {
                aplicarContratoVentaEnCampo(ptrFilaContratoVenta, data);
            }
            $('#consultacontratoventaModal').modal('hide');
        });

    $('#consultacontratoventaModal').off('shown.bs.modal.cvContrato hidden.bs.modal.cvContrato')
        .on('shown.bs.modal.cvContrato', function () {
            abriendoModalContratoVenta = false;
            $('#consultacontratoventa').focus();
        })
        .on('hidden.bs.modal.cvContrato', function () {
            abriendoModalContratoVenta = false;
        });
}

$(function () {
    if ($('#consultacontratoventaModal').length || $('.tm-contrato-venta-campo').length) {
        activa_eventos_consultacontratoventa();
    }
});
