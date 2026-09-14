var ptrTipotransaccionVentaContext;

function csrfTokenTipotransaccionVenta() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaTipotransaccionVenta(respuesta) {
    if (respuesta && typeof respuesta === 'object') {
        return respuesta.data || '';
    }
    var resp = String(respuesta || '');
    try {
        return JSON.parse(resp).data || '';
    } catch (e) {
        return resp;
    }
}

function actualizarLinkEditarTipotransaccionVenta($ctx, id) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-tipotransaccion-venta');
    if (!$link.length) {
        return;
    }
    var tid = parseInt(id, 10) || 0;
    if (tid > 0) {
        $link.attr('href', carpetaBase + '/ventas/tipotransaccion/' + tid + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarTipotransaccionVentaEnContexto($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.tipotransaccion_venta_id').val(data && data.id ? data.id : '');
    $ctx.find('.abreviaturatipotransaccionventa').val(data && data.abreviatura != null ? data.abreviatura : '');
    $ctx.find('.nombretipotransaccionventa').val(data && data.nombre != null ? data.nombre : '');
    actualizarLinkEditarTipotransaccionVenta($ctx, data && data.id ? data.id : 0);
}

function limpiarTipotransaccionVentaEnContexto($ctx, mantenerAbrev) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.tipotransaccion_venta_id').val('');
    if (!mantenerAbrev) {
        $ctx.find('.abreviaturatipotransaccionventa').val('');
    }
    $ctx.find('.nombretipotransaccionventa').val('');
    actualizarLinkEditarTipotransaccionVenta($ctx, 0);
}

function buscar_datos_tipotransaccion_venta(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/tipotransaccion/consultatipotransaccion',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenTipotransaccionVenta() },
        data: {
            consulta: consulta || '',
            _token: csrfTokenTipotransaccionVenta(),
        },
    })
        .done(function (respuesta) {
            $('#datostipotransaccionventa').html(parsearHtmlConsultaTipotransaccionVenta(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar tipos de transacci\u00f3n';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar tipos de transacci\u00f3n';
            }
            $('#datostipotransaccionventa').html('<tr><td colspan="5">' + msg + '</td></tr>');
        });
}

function resolverTipotransaccionVentaPorAbreviatura(abrev, $ctx, alertar) {
    var a = $.trim(abrev || '');
    if (a === '') {
        limpiarTipotransaccionVentaEnContexto($ctx, false);
        return;
    }
    $.ajax({
        url: carpetaBase + '/ventas/tipotransaccion/resolvertipotransaccion',
        type: 'GET',
        dataType: 'json',
        data: { abreviatura: a },
    })
        .done(function (data) {
            if (data && data.id) {
                aplicarTipotransaccionVentaEnContexto($ctx, data);
            } else {
                limpiarTipotransaccionVentaEnContexto($ctx, true);
                if (alertar) {
                    alert('Tipo de transacci\u00f3n no encontrado');
                }
            }
        })
        .fail(function () {
            limpiarTipotransaccionVentaEnContexto($ctx, true);
            if (alertar) {
                alert('Tipo de transacci\u00f3n no encontrado');
            }
        });
}

function activa_eventos_consultatipotransaccionventa() {
    $(document)
        .off('click.consultaTipoVenta', '.consultatipotransaccionventa')
        .on('click.consultaTipoVenta', '.consultatipotransaccionventa', function () {
            ptrTipotransaccionVentaContext = $(this).closest('.tm-tipotransaccion-venta-campo, tr');
            $('#consultatipotransaccionventaModal').modal('show');
        });

    $('#consultatipotransaccionventaModal')
        .off('shown.bs.modal.consultaTipoVenta')
        .on('shown.bs.modal.consultaTipoVenta', function () {
            var $input = $('#consultatipotransaccionventa');
            setTimeout(function () { $input.trigger('focus').select(); }, 0);
            buscar_datos_tipotransaccion_venta($input.val());
        });

    $(document)
        .off('keyup.consultaTipoVenta', '#consultatipotransaccionventa')
        .on('keyup.consultaTipoVenta', '#consultatipotransaccionventa', function (e) {
            if (e.which === 13 || e.key === 'Enter') {
                return;
            }
            buscar_datos_tipotransaccion_venta($(this).val());
        });

    $(document)
        .off('keydown.consultaTipoVenta', '#consultatipotransaccionventa')
        .on('keydown.consultaTipoVenta', '#consultatipotransaccionventa', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var $btn = $('#datostipotransaccionventa .eligeconsultatipotransaccionventa').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                buscar_datos_tipotransaccion_venta($(this).val());
            }
        });

    $(document)
        .off('click.eligeTipoVenta', '.eligeconsultatipotransaccionventa')
        .on('click.eligeTipoVenta', '.eligeconsultatipotransaccionventa', function () {
            var $tr = $(this).closest('tr');
            aplicarTipotransaccionVentaEnContexto(ptrTipotransaccionVentaContext, {
                id: $tr.find('.id').text().trim(),
                abreviatura: $tr.find('.abreviatura').text().trim(),
                nombre: $tr.find('.nombre').text().trim(),
            });
            $('#consultatipotransaccionventaModal').modal('hide');
        });

    $('#aceptaconsultatipotransaccionventaModal')
        .off('click.consultaTipoVenta')
        .on('click.consultaTipoVenta', function () {
            var $btn = $('#datostipotransaccionventa .eligeconsultatipotransaccionventa').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                $('#consultatipotransaccionventaModal').modal('hide');
            }
        });

    $(document)
        .off('keydown.abrevTipoVenta', '.abreviaturatipotransaccionventa')
        .on('keydown.abrevTipoVenta', '.abreviaturatipotransaccionventa', function (e) {
            var $input = $(this);
            if ($input.prop('readonly') || $input.prop('disabled')) {
                return;
            }
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                $input.closest('.tm-tipotransaccion-venta-campo').find('.consultatipotransaccionventa').first().trigger('click');
                return;
            }
            if (e.which === 13 || e.key === 'Enter') {
                e.preventDefault();
                resolverTipotransaccionVentaPorAbreviatura($input.val(), $input.closest('.tm-tipotransaccion-venta-campo'), true);
            }
        });

    $(document)
        .off('blur.abrevTipoVenta', '.abreviaturatipotransaccionventa')
        .on('blur.abrevTipoVenta', '.abreviaturatipotransaccionventa', function () {
            if ($('#consultatipotransaccionventaModal').hasClass('show')) {
                return;
            }
            var $input = $(this);
            var $ctx = $input.closest('.tm-tipotransaccion-venta-campo');
            var idActual = parseInt(String($ctx.find('.tipotransaccion_venta_id').val() || '0'), 10);
            var abrev = $.trim($input.val() || '');
            if (abrev === '') {
                limpiarTipotransaccionVentaEnContexto($ctx, false);
                return;
            }
            if (idActual > 0) {
                return;
            }
            resolverTipotransaccionVentaPorAbreviatura(abrev, $ctx, false);
        });

    $(document)
        .off('input.abrevTipoVenta', '.abreviaturatipotransaccionventa')
        .on('input.abrevTipoVenta', '.abreviaturatipotransaccionventa', function () {
            var $ctx = $(this).closest('.tm-tipotransaccion-venta-campo');
            $ctx.find('.tipotransaccion_venta_id').val('');
            $ctx.find('.nombretipotransaccionventa').val('');
            actualizarLinkEditarTipotransaccionVenta($ctx, 0);
        });
}

$(function () {
    if ($('.tm-tipotransaccion-venta-campo, .consultatipotransaccionventa').length) {
        activa_eventos_consultatipotransaccionventa();
    }
});
