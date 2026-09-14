var ptrPuntoventaContext;

function csrfTokenPuntoventa() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaPuntoventa(respuesta) {
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

function actualizarLinkEditarPuntoventa($ctx, id) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-puntoventa');
    if (!$link.length) {
        return;
    }
    var pvId = parseInt(id, 10) || 0;
    if (pvId > 0) {
        $link.attr('href', carpetaBase + '/ventas/puntoventa/' + pvId + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarPuntoventaEnContexto($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.puntoventa_id').val(data && data.id ? data.id : '');
    $ctx.find('.codigopuntoventa').val(data && data.codigo != null ? data.codigo : '');
    $ctx.find('.descripcionpuntoventa').val(data && data.nombre != null ? data.nombre : '');
    actualizarLinkEditarPuntoventa($ctx, data && data.id ? data.id : 0);
    if (typeof window.onPuntoventaSeleccionadoLocalVenta === 'function') {
        window.onPuntoventaSeleccionadoLocalVenta($ctx, data);
    }
}

function limpiarPuntoventaEnContexto($ctx, mantenerCodigo) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.puntoventa_id').val('');
    if (!mantenerCodigo) {
        $ctx.find('.codigopuntoventa').val('');
    }
    $ctx.find('.descripcionpuntoventa').val('');
    actualizarLinkEditarPuntoventa($ctx, 0);
}

function buscar_datos_puntoventa(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/puntoventa/consultapuntoventa',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenPuntoventa() },
        data: {
            consulta: consulta || '',
            empresa_id: ($('#empresa_id').val() || ''),
            _token: csrfTokenPuntoventa(),
        },
    })
        .done(function (respuesta) {
            $('#datospuntoventa').html(parsearHtmlConsultaPuntoventa(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar puntos de venta';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar puntos de venta';
            }
            $('#datospuntoventa').html('<tr><td colspan="5">' + msg + '</td></tr>');
        });
}

function resolverPuntoventaPorCodigo(codigo, $ctx, alertar) {
    var cod = $.trim(codigo || '');
    if (cod === '') {
        limpiarPuntoventaEnContexto($ctx, false);
        return;
    }
    $.ajax({
        url: carpetaBase + '/ventas/puntoventa/resolverpuntoventa',
        type: 'GET',
        dataType: 'json',
        data: { codigo: cod, empresa_id: ($('#empresa_id').val() || '') },
    })
        .done(function (data) {
            if (data && data.id) {
                aplicarPuntoventaEnContexto($ctx, data);
            } else {
                limpiarPuntoventaEnContexto($ctx, true);
                if (alertar) {
                    alert('Punto de venta no encontrado');
                }
            }
        })
        .fail(function () {
            limpiarPuntoventaEnContexto($ctx, true);
            if (alertar) {
                alert('Punto de venta no encontrado');
            }
        });
}

function activa_eventos_consultapuntoventa() {
    $(document)
        .off('click.consultaPuntoventa', '.consultapuntoventa')
        .on('click.consultaPuntoventa', '.consultapuntoventa', function () {
            ptrPuntoventaContext = $(this).closest('.tm-puntoventa-campo, tr');
            $('#consultapuntoventaModal').modal('show');
        });

    $('#consultapuntoventaModal')
        .off('shown.bs.modal.consultaPuntoventa')
        .on('shown.bs.modal.consultaPuntoventa', function () {
            var $input = $('#consultapuntoventa');
            setTimeout(function () { $input.trigger('focus').select(); }, 0);
            buscar_datos_puntoventa($input.val());
        });

    $(document)
        .off('keyup.consultaPuntoventa', '#consultapuntoventa')
        .on('keyup.consultaPuntoventa', '#consultapuntoventa', function (e) {
            if (e.which === 13 || e.key === 'Enter') {
                return;
            }
            buscar_datos_puntoventa($(this).val());
        });

    $(document)
        .off('keydown.consultaPuntoventa', '#consultapuntoventa')
        .on('keydown.consultaPuntoventa', '#consultapuntoventa', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var $btn = $('#datospuntoventa .eligeconsultapuntoventa').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                buscar_datos_puntoventa($(this).val());
            }
        });

    $(document)
        .off('click.eligePuntoventa', '.eligeconsultapuntoventa')
        .on('click.eligePuntoventa', '.eligeconsultapuntoventa', function () {
            var $tr = $(this).closest('tr');
            aplicarPuntoventaEnContexto(ptrPuntoventaContext, {
                id: $tr.find('.id').text().trim(),
                codigo: $tr.find('.codigo').text().trim(),
                nombre: $tr.find('.nombre').text().trim(),
            });
            $('#consultapuntoventaModal').modal('hide');
        });

    $('#aceptaconsultapuntoventaModal')
        .off('click.consultaPuntoventa')
        .on('click.consultaPuntoventa', function () {
            var $btn = $('#datospuntoventa .eligeconsultapuntoventa').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                $('#consultapuntoventaModal').modal('hide');
            }
        });

    $(document)
        .off('keydown.codigoPuntoventa', '.codigopuntoventa')
        .on('keydown.codigoPuntoventa', '.codigopuntoventa', function (e) {
            var $input = $(this);
            if ($input.prop('readonly') || $input.prop('disabled')) {
                return;
            }
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                $input.closest('.tm-puntoventa-campo, tr').find('.consultapuntoventa').first().trigger('click');
                return;
            }
            if (e.which === 13 || e.key === 'Enter') {
                e.preventDefault();
                resolverPuntoventaPorCodigo($input.val(), $input.closest('.tm-puntoventa-campo, tr'), true);
            }
        });

    $(document)
        .off('blur.codigoPuntoventa', '.codigopuntoventa')
        .on('blur.codigoPuntoventa', '.codigopuntoventa', function () {
            if ($('#consultapuntoventaModal').hasClass('show')) {
                return;
            }
            var $input = $(this);
            var $ctx = $input.closest('.tm-puntoventa-campo, tr');
            var idActual = parseInt(String($ctx.find('.puntoventa_id').val() || '0'), 10);
            var cod = $.trim($input.val() || '');
            if (cod === '') {
                limpiarPuntoventaEnContexto($ctx, false);
                return;
            }
            if (idActual > 0) {
                return;
            }
            resolverPuntoventaPorCodigo(cod, $ctx, false);
        });

    $(document)
        .off('input.codigoPuntoventa', '.codigopuntoventa')
        .on('input.codigoPuntoventa', '.codigopuntoventa', function () {
            var $ctx = $(this).closest('.tm-puntoventa-campo, tr');
            $ctx.find('.puntoventa_id').val('');
            $ctx.find('.descripcionpuntoventa').val('');
            actualizarLinkEditarPuntoventa($ctx, 0);
        });
}

$(function () {
    if ($('.tm-puntoventa-campo, .consultapuntoventa').length) {
        activa_eventos_consultapuntoventa();
    }
});
