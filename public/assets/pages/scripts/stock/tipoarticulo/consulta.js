var ptrTipoarticuloContext;

function csrfTokenTipoarticulo() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaTipoarticulo(respuesta) {
    if (respuesta && typeof respuesta === 'object' && respuesta.data != null) {
        return respuesta.data;
    }
    var resp = String(respuesta || '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function actualizarLinkEditarTipoarticulo($ctx, tipoId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-tipoarticulo');
    if (!$link.length) {
        return;
    }
    var id = parseInt(tipoId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/tipoarticulo/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarTipoarticuloEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : '';
    var nombre = data && data.nombre != null ? data.nombre : '';
    if ($ctx && $ctx.length) {
        $ctx.find('.tipoarticulo_id').val(id);
        $ctx.find('.codigotipoarticulo').val(codigo).removeAttr('data-tipoarticulo-invalido');
        $ctx.find('.nombretipoarticulo').val(nombre);
        actualizarLinkEditarTipoarticulo($ctx, id);
    }
    if (opts.avanzar && id) {
        var sig = $ctx && $ctx.length ? $ctx.find('.codigotipoarticulo').attr('data-siguiente') : '';
        if (sig && $(sig).length) {
            $(sig).trigger('focus');
        }
    }
}

function limpiarTipoarticuloEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.tipoarticulo_id').val('');
    $ctx.find('.codigotipoarticulo').val('').removeAttr('data-tipoarticulo-invalido');
    $ctx.find('.nombretipoarticulo').val('');
    actualizarLinkEditarTipoarticulo($ctx, 0);
}

function modalConsultaTipoarticuloAbierto() {
    var $m = $('#consultatipoarticuloModal');
    return $m.length && $m.hasClass('show');
}

function buscar_datos_tipoarticulo(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/tipoarticulo/consultatipoarticulo',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenTipoarticulo() },
        data: { consulta: consulta || '', _token: csrfTokenTipoarticulo() },
    })
        .done(function (respuesta) {
            $('#datostipoarticulo').html(parsearHtmlConsultaTipoarticulo(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar tipos';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar tipos';
            }
            $('#datostipoarticulo').html('<tr><td colspan="4">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoTipoarticulo(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (cod === '') {
        limpiarTipoarticuloEnContexto($ctx);
        if (opts.avanzar) {
            var sigVacio = $ctx.find('.codigotipoarticulo').attr('data-siguiente');
            if (sigVacio && $(sigVacio).length) {
                $(sigVacio).trigger('focus');
            }
        }
        return;
    }

    $.get(carpetaBase + '/stock/leertipoarticulo/' + encodeURIComponent(cod), function (data) {
        if (data && data.id) {
            aplicarTipoarticuloEnContexto($ctx, data, opts);
            return;
        }
        var $codigo = $ctx.find('.codigotipoarticulo');
        $ctx.find('.tipoarticulo_id').val('');
        $ctx.find('.nombretipoarticulo').val('');
        $codigo.val(cod).attr('data-tipoarticulo-invalido', cod);
        actualizarLinkEditarTipoarticulo($ctx, 0);
    });
}

function abrirModalConsultaTipoarticuloDesdeInput($input) {
    ptrTipoarticuloContext = $input.closest('.tm-tipoarticulo-campo');
    $('#consultatipoarticulo').val('');
    $('#consultatipoarticuloModal').modal('show');
    buscar_datos_tipoarticulo('');
}

function elegirPrimerTipoarticuloDelModal() {
    var $btn = $('#datostipoarticulo .eligeconsultatipoarticulo').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

function activa_eventos_consultatipoarticulo() {
    var $modal = $('#consultatipoarticuloModal');
    if ($modal.length && $modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }

    $(document).off('mousedown.tipoarticuloLupa', '.consultatipoarticulo').on('mousedown.tipoarticuloLupa', '.consultatipoarticulo', function () {
        $(this).closest('.tm-tipoarticulo-campo').find('.codigotipoarticulo').data('consulta-abriendo', 1);
    });

    $(document).off('click.tipoarticuloLupa', '.consultatipoarticulo').on('click.tipoarticuloLupa', '.consultatipoarticulo', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaTipoarticuloDesdeInput($(this));
    });

    $modal.off('shown.bs.modal.tipoarticulo').on('shown.bs.modal.tipoarticulo', function () {
        $('#consultatipoarticulo').trigger('focus');
    });

    $(document).off('keyup.consultatipoarticulo', '#consultatipoarticulo').on('keyup.consultatipoarticulo', '#consultatipoarticulo', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        buscar_datos_tipoarticulo($(this).val());
    });

    $(document).off('keydown.consultatipoarticuloEnter', '#consultatipoarticulo').on('keydown.consultatipoarticuloEnter', '#consultatipoarticulo', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimerTipoarticuloDelModal()) {
            buscar_datos_tipoarticulo($(this).val());
        }
    });

    $('#aceptaconsultatipoarticuloModal').off('click.tipoarticulo').on('click.tipoarticulo', function () {
        if (!elegirPrimerTipoarticuloDelModal()) {
            $('#consultatipoarticuloModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultatipoarticulo', '.eligeconsultatipoarticulo').on('click.eligeconsultatipoarticulo', '.eligeconsultatipoarticulo', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            codigo: $.trim($row.find('.codigo').text()),
            nombre: $.trim($row.find('.nombre').text()),
        };
        var $ctx = ptrTipoarticuloContext && ptrTipoarticuloContext.length
            ? ptrTipoarticuloContext
            : $(this).closest('.tm-tipoarticulo-campo');
        aplicarTipoarticuloEnContexto($ctx, data, { avanzar: false });
        $('#consultatipoarticuloModal').modal('hide');
    });

    $(document).off('input.tipoarticuloCodigo', '.codigotipoarticulo').on('input.tipoarticuloCodigo', '.codigotipoarticulo', function () {
        $(this).removeAttr('data-tipoarticulo-invalido');
    });

    $(document).off('blur.tipoarticuloCodigo', '.codigotipoarticulo').on('blur.tipoarticuloCodigo', '.codigotipoarticulo', function () {
        var $input = $(this);
        if ($input.data('consulta-abriendo') || modalConsultaTipoarticuloAbierto()) {
            $input.removeData('consulta-abriendo');
            return;
        }
        resolverPorCodigoTipoarticulo($input.val(), $input.closest('.tm-tipoarticulo-campo'), { silencioso: true });
    });

    $(document).off('keydown.tipoarticuloCodigo', '.codigotipoarticulo').on('keydown.tipoarticuloCodigo', '.codigotipoarticulo', function (e) {
        var $input = $(this);
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            if (modalConsultaTipoarticuloAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaTipoarticuloDesdeInput($input);
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoTipoarticulo($input.val(), $input.closest('.tm-tipoarticulo-campo'), {
            silencioso: false,
            avanzar: true,
        });
    });
}

$(function () {
    if ($('#consultatipoarticuloModal').length || $('.consultatipoarticulo').length || $('.tm-tipoarticulo-campo').length) {
        activa_eventos_consultatipoarticulo();
    }
});
