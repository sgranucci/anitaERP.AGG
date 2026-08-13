var ptrTipotransaccionCompra_id;
var ptrAbreviaturaTipotransaccionCompra;
var ptrNombreTipotransaccionCompra;

function buscar_datos_tipotransaccion_compra(consulta) {
    var payload = {
        consulta: consulta || '',
    };
    if (typeof window.payloadExtraConsultaTipotransaccionCompra === 'function') {
        $.extend(payload, window.payloadExtraConsultaTipotransaccionCompra());
    }

    $.ajax({
        url: carpetaBase + '/compras/tipotransaccion_compra/consultatipotransaccion',
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
            $('#datostipotransaccioncompra').html(html);
        })
        .fail(function () {
            $('#datostipotransaccioncompra').html('<tr><td colspan="4">Error al consultar tipos de comprobante</td></tr>');
        });
}

$(document).on('keyup', '#consultatipotransaccioncompra', function () {
    buscar_datos_tipotransaccion_compra($(this).val());
});

var capturaEnterAbreviaturaTipotransaccionCompraActiva = false;

function aplicarTipotransaccionCompraElegido(id, abreviatura, nombre) {
    if (ptrTipotransaccionCompra_id && ptrTipotransaccionCompra_id.length) {
        var prev = String(ptrTipotransaccionCompra_id.val() || '');
        ptrTipotransaccionCompra_id.val(id || '');
        if (ptrAbreviaturaTipotransaccionCompra && ptrAbreviaturaTipotransaccionCompra.length) {
            ptrAbreviaturaTipotransaccionCompra.val(abreviatura || '');
        }
        if (ptrNombreTipotransaccionCompra && ptrNombreTipotransaccionCompra.length) {
            ptrNombreTipotransaccionCompra.val(nombre || '');
        }
        var $ctx = ptrTipotransaccionCompra_id.closest('.tm-tipotransaccion-compra-campo');
        var $link = $ctx.find('.btn-link-editar-tipotransaccion-compra');
        if ($link.length) {
            if (id) {
                $link.removeClass('d-none').attr(
                    'href',
                    carpetaBase + '/compras/tipotransaccion_compra/' + id + '/editar?origen=modal_consulta&vista=consulta'
                );
            } else {
                $link.addClass('d-none').attr('href', '#');
            }
        }
        if (String(id || '') !== prev) {
            $(document).trigger('cp:tipotransaccion-compra-elegido', [parseInt(id || '0', 10) || 0]);
        }
    }
}

function leerTipotransaccionCompraPorAbreviatura(abreviatura, target, callback) {
    var abrev = String(abreviatura || '').trim();
    if (abrev === '') {
        aplicarTipotransaccionCompraElegido('', '', '');
        if (typeof callback === 'function') {
            callback(null);
        }
        return;
    }

    var payload = {};
    if (typeof window.payloadExtraConsultaTipotransaccionCompra === 'function') {
        $.extend(payload, window.payloadExtraConsultaTipotransaccionCompra());
    }

    $.ajax({
        url: carpetaBase + '/compras/tipotransaccion_compra/leer/' + encodeURIComponent(abrev),
        type: 'GET',
        dataType: 'json',
        data: payload,
    })
        .done(function (data) {
            if (!data || !data.id) {
                alert('No se encontró el tipo de comprobante «' + abrev + '».');
                aplicarTipotransaccionCompraElegido('', '', '');
                if (target) {
                    $(target).val('').trigger('focus');
                }
                if (typeof callback === 'function') {
                    callback(null);
                }
                return;
            }
            aplicarTipotransaccionCompraElegido(data.id, data.abreviatura || abrev, data.nombre || '');
            if (typeof callback === 'function') {
                callback(data);
            }
        })
        .fail(function () {
            alert('Error al validar la abreviatura del tipo de comprobante.');
            if (typeof callback === 'function') {
                callback(null);
            }
        });
}

function manejarEnterAbreviaturaTipotransaccionCompra(e) {
    var target = e.target;
    if (!target || !target.classList || !target.classList.contains('abreviaturatipotransaccioncompra')) {
        return;
    }
    if (target.readOnly || target.disabled) {
        return;
    }

    if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(target).closest('.tm-tipotransaccion-compra-campo, tr').find('.consultatipotransaccioncompra').first().trigger('click');
        return;
    }

    if (e.which !== 13 && e.key !== 'Enter') {
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    var $ctx = $(target).closest('.tm-tipotransaccion-compra-campo, tr');
    ptrTipotransaccionCompra_id = $ctx.find('.tipotransaccion_compra_id');
    ptrAbreviaturaTipotransaccionCompra = $ctx.find('.abreviaturatipotransaccioncompra');
    ptrNombreTipotransaccionCompra = $ctx.find('.nombretipotransaccioncompra');

    leerTipotransaccionCompraPorAbreviatura(target.value, target);
}

function activarCapturaEnterAbreviaturaTipotransaccionCompra() {
    if (capturaEnterAbreviaturaTipotransaccionCompraActiva) {
        return;
    }
    document.addEventListener('keydown', manejarEnterAbreviaturaTipotransaccionCompra, true);
    capturaEnterAbreviaturaTipotransaccionCompraActiva = true;
}

function activa_eventos_consultatipotransaccioncompra() {
    activarCapturaEnterAbreviaturaTipotransaccionCompra();

    $('.consultatipotransaccioncompra')
        .off('click.consultaTipotransaccionCompra')
        .on('click.consultaTipotransaccionCompra', function () {
            var $btn = $(this);
            var $ctx = $btn.closest('.tm-tipotransaccion-compra-campo, tr');

            ptrTipotransaccionCompra_id = $ctx.find('.tipotransaccion_compra_id');
            ptrAbreviaturaTipotransaccionCompra = $ctx.find('.abreviaturatipotransaccioncompra');
            ptrNombreTipotransaccionCompra = $ctx.find('.nombretipotransaccioncompra');

            $('#consultatipotransaccioncompraModal')
                .removeAttr('inert')
                .css('display', '')
                .modal('show');
        });

    $('#consultatipotransaccioncompraModal')
        .off('shown.bs.modal.consultaTipotransaccionCompra')
        .on('shown.bs.modal.consultaTipotransaccionCompra', function () {
            $(this).removeAttr('inert');
            var $input = $('#consultatipotransaccioncompra');
            setTimeout(function () {
                $input.trigger('focus').select();
            }, 0);
            buscar_datos_tipotransaccion_compra($input.val());
        });

    $('#aceptaconsultatipotransaccioncompraModal')
        .off('click.consultaTipotransaccionCompra')
        .on('click.consultaTipotransaccionCompra', function () {
            $('#consultatipotransaccioncompraModal').modal('hide');
        });

    $(document)
        .off('click.eligeconsultatipotransaccioncompra')
        .on('click', '.eligeconsultatipotransaccioncompra', function () {
            var $tr = $(this).parents('tr');
            aplicarTipotransaccionCompraElegido(
                $tr.find('.id').html(),
                $tr.find('.abreviatura').html(),
                $tr.find('.nombre').html()
            );
            $('#consultatipotransaccioncompraModal').modal('hide');
        });

    $(document)
        .off('blur.abreviaturaTipotransaccionCompra')
        .on('blur', '.abreviaturatipotransaccioncompra', function () {
            var target = this;
            if (target.readOnly || target.disabled) {
                return;
            }
            var $ctx = $(target).closest('.tm-tipotransaccion-compra-campo, tr');
            ptrTipotransaccionCompra_id = $ctx.find('.tipotransaccion_compra_id');
            ptrAbreviaturaTipotransaccionCompra = $ctx.find('.abreviaturatipotransaccioncompra');
            ptrNombreTipotransaccionCompra = $ctx.find('.nombretipotransaccioncompra');
            var actualId = String(ptrTipotransaccionCompra_id.val() || '');
            var abrev = String(target.value || '').trim();
            if (abrev === '') {
                if (actualId !== '') {
                    aplicarTipotransaccionCompraElegido('', '', '');
                }
                return;
            }
            if (actualId !== '' && String(ptrAbreviaturaTipotransaccionCompra.data('ultima-valida') || '') === abrev) {
                return;
            }
            leerTipotransaccionCompraPorAbreviatura(abrev, target, function (data) {
                if (data && data.abreviatura) {
                    ptrAbreviaturaTipotransaccionCompra.data('ultima-valida', data.abreviatura);
                }
            });
        });
}
