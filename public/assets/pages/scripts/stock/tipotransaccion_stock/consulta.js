var ptrTipotransaccionStock_id;
var ptrAbreviaturaTipotransaccionStock;
var ptrNombreTipotransaccionStock;

function buscar_datos_tipotransaccion_stock(consulta) {
    var payload = {
        consulta: consulta || '',
    };
    if (typeof window.payloadExtraConsultaTipotransaccionStock === 'function') {
        $.extend(payload, window.payloadExtraConsultaTipotransaccionStock());
    }

    $.ajax({
        url: carpetaBase + '/stock/tipotransaccion_stock/consultatipotransaccion',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: payload,
    })
        .done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            } else if (respuesta && typeof respuesta.data === 'string') {
                html = respuesta.data;
            }
            $('#datostipotransaccionstock').html(html);
        })
        .fail(function () {
            $('#datostipotransaccionstock').html('<tr><td colspan="5">Error al consultar tipos de transacci&oacute;n</td></tr>');
        });
}

$(document).on('keyup', '#consultatipotransaccionstock', function () {
    buscar_datos_tipotransaccion_stock($(this).val());
});

var capturaEnterAbreviaturaTipotransaccionStockActiva = false;

function manejarEnterAbreviaturaTipotransaccionStock(e) {
    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }

    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('abreviaturatipotransaccionstock')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    leerTipotransaccionStockPorAbreviatura(target.value, target, function (data) {
        if (!data || !data.id) {
            return;
        }
        if (!$(target).closest('#tm_tipotransaccion_movimientostock').length) {
            return;
        }
        if (typeof window.enfocarSiguienteCampoTrasTipoTransaccionMov === 'function') {
            window.enfocarSiguienteCampoTrasTipoTransaccionMov();
        }
    });
}

function activarCapturaEnterAbreviaturaTipotransaccionStock() {
    if (capturaEnterAbreviaturaTipotransaccionStockActiva) {
        return;
    }
    document.addEventListener('keydown', manejarEnterAbreviaturaTipotransaccionStock, true);
    capturaEnterAbreviaturaTipotransaccionStockActiva = true;
}

    function activa_eventos_consultatipotransaccionstock() {
    $('.consultatipotransaccionstock')
        .off('click.consultaTipotransaccionStock')
        .on('click.consultaTipotransaccionStock', function () {
            var $btn = $(this);
            var $ctx = $btn.closest('.tm-tipotransaccion-stock-campo, tr');

            ptrTipotransaccionStock_id = $ctx.find('.tipotransaccion_stock_id');
            if (!ptrTipotransaccionStock_id.length) {
                ptrTipotransaccionStock_id = $btn.parents('tr').find('.tipotransaccion_stock_id');
            }

            ptrAbreviaturaTipotransaccionStock = $ctx.find('.abreviaturatipotransaccionstock');
            if (!ptrAbreviaturaTipotransaccionStock.length) {
                ptrAbreviaturaTipotransaccionStock = $btn.parents('tr').find('.abreviaturatipotransaccionstock');
            }

            ptrNombreTipotransaccionStock = $ctx.find('.nombretipotransaccionstock');
            if (!ptrNombreTipotransaccionStock.length) {
                ptrNombreTipotransaccionStock = $btn.parents('tr').find('.nombretipotransaccionstock');
            }

            $('#consultatipotransaccionstockModal')
                .removeAttr('inert')
                .css('display', '')
                .modal('show');
        });

    $('#consultatipotransaccionstockModal')
        .off('shown.bs.modal.consultaTipotransaccionStock')
        .on('shown.bs.modal.consultaTipotransaccionStock', function () {
            $(this).removeAttr('inert');
            var $input = $('#consultatipotransaccionstock');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
            buscar_datos_tipotransaccion_stock($input.val());
        });

    $('#aceptaconsultatipotransaccionstockModal')
        .off('click.consultaTipotransaccionStock')
        .on('click.consultaTipotransaccionStock', function () {
            $('#consultatipotransaccionstockModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultatipotransaccionstock')
        .on('click', '.eligeconsultatipotransaccionstock', function () {
            var $tr = $(this).parents('tr');
            var id = $tr.find('.id').html();
            var abreviatura = $tr.find('.abreviatura').html();
            var nombre = $tr.find('.nombre').html();
            var operacionCodigo = $tr.find('.operacion-codigo').html() || '';
            var manejaCont = $tr.find('.maneja-contabilidad').html() || '0';
            var origenBien = $tr.find('.origen-bien-uso').html() || '0';
            var destinoBien = $tr.find('.destino-bien-uso').html() || '0';

            if ($('#tipotransaccion_stock_id').length && ptrTipotransaccionStock_id
                && ptrTipotransaccionStock_id.attr('id') === 'tipotransaccion_stock_id'
                && typeof window.msAplicarTipotransaccionStockEnCampo === 'function') {
                window.msAplicarTipotransaccionStockEnCampo({
                    id: id,
                    abreviatura: abreviatura,
                    nombre: nombre,
                    operacion: operacionCodigo,
                    maneja_contabilidad: manejaCont === '1',
                    origen_bien_uso: origenBien === '1',
                    destino_bien_uso: destinoBien === '1',
                });
                $('#consultatipotransaccionstockModal').modal('hide');
                return;
            }

            if (ptrTipotransaccionStock_id && ptrTipotransaccionStock_id.length
                && ptrTipotransaccionStock_id.closest('#tbody-usuario-tipotransaccion-stock-table').length) {
                var tipoId = String(id || '').trim();
                var $trTabla = ptrTipotransaccionStock_id.closest('tr');
                var duplicado = false;
                $('#tbody-usuario-tipotransaccion-stock-table .tipotransaccion_stock_id').each(function () {
                    if ($(this).closest('tr').is($trTabla)) {
                        return;
                    }
                    if (String($(this).val() || '') === tipoId) {
                        duplicado = true;
                    }
                });
                if (duplicado) {
                    alert('Tipo de transacci\u00f3n ya cargado');
                    $('#consultatipotransaccionstockModal').modal('hide');
                    return;
                }
            }

            if (ptrTipotransaccionStock_id && ptrTipotransaccionStock_id.length) {
                ptrTipotransaccionStock_id.val(id);
            }
            if (ptrAbreviaturaTipotransaccionStock && ptrAbreviaturaTipotransaccionStock.length) {
                ptrAbreviaturaTipotransaccionStock.val(abreviatura);
            }
            if (ptrNombreTipotransaccionStock && ptrNombreTipotransaccionStock.length) {
                ptrNombreTipotransaccionStock.val(nombre);
            }
            if (ptrTipotransaccionStock_id && ptrTipotransaccionStock_id.length) {
                ptrTipotransaccionStock_id.closest('tr').find('.operacion-tipotransaccion-stock').val($tr.find('.operacion').html() || '');
            }

            $('#consultatipotransaccionstockModal').modal('hide');
        });

    $(document)
        .off('change.leerTipotransaccionStockAbrev', '.abreviaturatipotransaccionstock')
        .on('change.leerTipotransaccionStockAbrev', '.abreviaturatipotransaccionstock', function (e) {
            e.preventDefault();
            leerTipotransaccionStockPorAbreviatura($(this).val(), this);
        });
}

function leerTipotransaccionStockPorAbreviatura(abreviatura, ptrrenglon, onDone) {
    var abrev = (abreviatura || '').trim();
    if (!abrev) {
        if (typeof onDone === 'function') {
            onDone(null);
        }
        return;
    }

    var $ctx = $(ptrrenglon).closest('.tm-tipotransaccion-stock-campo, tr');
    var abrevOriginal = abrev;
    if ($ctx.length) {
        $ctx.find('.tipotransaccion_stock_id').val('');
        $ctx.find('.nombretipotransaccionstock').val('');
    }

    var leerUrl = carpetaBase + '/stock/tipotransaccion_stock/leer/' + encodeURIComponent(abrev);
    var extraPayload = typeof window.payloadExtraConsultaTipotransaccionStock === 'function'
        ? window.payloadExtraConsultaTipotransaccionStock()
        : {};
    if (extraPayload.omitir_filtro_usuario) {
        leerUrl += (leerUrl.indexOf('?') >= 0 ? '&' : '?') + 'omitir_filtro_usuario=1';
    }

    $.get(leerUrl)
        .done(function (data) {
            if (!data || !data.id) {
                if ($ctx.length) {
                    $ctx.find('.abreviaturatipotransaccionstock').val(abrevOriginal);
                }
                alert('Tipo de transacci\u00f3n no encontrado');
                if (typeof onDone === 'function') {
                    onDone(null);
                }
                return;
            }

            if ($ctx.length) {
                if ($('#tipotransaccion_stock_id').length && $ctx.find('#tipotransaccion_stock_id').length
                    && typeof window.msAplicarTipotransaccionStockEnCampo === 'function') {
                    window.msAplicarTipotransaccionStockEnCampo(data);
                    if (typeof onDone === 'function') {
                        onDone(data);
                    }
                    return;
                }

                $ctx.find('.tipotransaccion_stock_id').val(data.id);
                $ctx.find('.abreviaturatipotransaccionstock').val(data.abreviatura);
                $ctx.find('.nombretipotransaccionstock').val(data.nombre);
                $ctx.find('.operacion-tipotransaccion-stock').val(data.operacion_etiqueta || data.operacion || '');
            }
            if (typeof onDone === 'function') {
                onDone(data);
            }
        })
        .fail(function () {
            if ($ctx.length) {
                $ctx.find('.abreviaturatipotransaccionstock').val(abrevOriginal);
            }
            if (typeof onDone === 'function') {
                onDone(null);
            }
        });
}

$(function () {
    activarCapturaEnterAbreviaturaTipotransaccionStock();
    if (typeof activa_eventos_consultatipotransaccionstock === 'function') {
        activa_eventos_consultatipotransaccionstock();
    }
});
