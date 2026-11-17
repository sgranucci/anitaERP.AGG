var ptrUsoarticuloContext;

function csrfTokenUsoarticulo() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaUsoarticulo(respuesta) {
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

function actualizarLinkEditarUsoarticulo($ctx, usoId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-usoarticulo');
    if (!$link.length) {
        return;
    }
    var id = parseInt(usoId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/usoarticulo/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarUsoarticuloEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : (id !== '' ? String(id) : '');
    var nombre = data && data.nombre != null ? data.nombre : '';
    if ($ctx && $ctx.length) {
        $ctx.find('.usoarticulo_id').val(id);
        $ctx.find('.codigousoarticulo').val(codigo).removeAttr('data-usoarticulo-invalido');
        $ctx.find('.nombreusoarticulo').val(nombre);
        actualizarLinkEditarUsoarticulo($ctx, id);
    }
    if (opts.avanzar && id) {
        var sig = $ctx && $ctx.length ? $ctx.find('.codigousoarticulo').attr('data-siguiente') : '';
        if (sig && $(sig).length) {
            $(sig).trigger('focus');
        }
    }
}

function limpiarUsoarticuloEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.usoarticulo_id').val('');
    $ctx.find('.codigousoarticulo').val('').removeAttr('data-usoarticulo-invalido');
    $ctx.find('.nombreusoarticulo').val('');
    actualizarLinkEditarUsoarticulo($ctx, 0);
}

function modalConsultaUsoarticuloAbierto() {
    var $m = $('#consultausoarticuloModal');
    return $m.length && $m.hasClass('show');
}

function buscar_datos_usoarticulo(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/usoarticulo/consultausoarticulo',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenUsoarticulo() },
        data: { consulta: consulta || '', _token: csrfTokenUsoarticulo() },
    })
        .done(function (respuesta) {
            $('#datosusoarticulo').html(parsearHtmlConsultaUsoarticulo(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar usos';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar usos';
            }
            $('#datosusoarticulo').html('<tr><td colspan="3">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoUsoarticulo(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (cod === '') {
        limpiarUsoarticuloEnContexto($ctx);
        if (opts.avanzar) {
            var sigVacio = $ctx.find('.codigousoarticulo').attr('data-siguiente');
            if (sigVacio && $(sigVacio).length) {
                $(sigVacio).trigger('focus');
            }
        }
        return;
    }

    $.get(carpetaBase + '/stock/leerusoarticulo/' + encodeURIComponent(cod), function (data) {
        if (data && data.id) {
            aplicarUsoarticuloEnContexto($ctx, data, opts);
            return;
        }
        var $codigo = $ctx.find('.codigousoarticulo');
        $ctx.find('.usoarticulo_id').val('');
        $ctx.find('.nombreusoarticulo').val('');
        $codigo.val(cod).attr('data-usoarticulo-invalido', cod);
        actualizarLinkEditarUsoarticulo($ctx, 0);
    });
}

function abrirModalConsultaUsoarticuloDesdeInput($input) {
    ptrUsoarticuloContext = $input.closest('.tm-usoarticulo-campo');
    $('#consultausoarticulo').val('');
    $('#consultausoarticuloModal').modal('show');
    buscar_datos_usoarticulo('');
}

function elegirPrimerUsoarticuloDelModal() {
    var $btn = $('#datosusoarticulo .eligeconsultausoarticulo').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

function activa_eventos_consultausoarticulo() {
    var $modal = $('#consultausoarticuloModal');
    if ($modal.length && $modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }

    $(document).off('mousedown.usoarticuloLupa', '.consultausoarticulo').on('mousedown.usoarticuloLupa', '.consultausoarticulo', function () {
        $(this).closest('.tm-usoarticulo-campo').find('.codigousoarticulo').data('consulta-abriendo', 1);
    });

    $(document).off('click.usoarticuloLupa', '.consultausoarticulo').on('click.usoarticuloLupa', '.consultausoarticulo', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaUsoarticuloDesdeInput($(this));
    });

    $modal.off('shown.bs.modal.usoarticulo').on('shown.bs.modal.usoarticulo', function () {
        $('#consultausoarticulo').trigger('focus');
    });

    $(document).off('keyup.consultausoarticulo', '#consultausoarticulo').on('keyup.consultausoarticulo', '#consultausoarticulo', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        buscar_datos_usoarticulo($(this).val());
    });

    $(document).off('keydown.consultausoarticuloEnter', '#consultausoarticulo').on('keydown.consultausoarticuloEnter', '#consultausoarticulo', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimerUsoarticuloDelModal()) {
            buscar_datos_usoarticulo($(this).val());
        }
    });

    $('#aceptaconsultausoarticuloModal').off('click.usoarticulo').on('click.usoarticulo', function () {
        if (!elegirPrimerUsoarticuloDelModal()) {
            $('#consultausoarticuloModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultausoarticulo', '.eligeconsultausoarticulo').on('click.eligeconsultausoarticulo', '.eligeconsultausoarticulo', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var id = $.trim($row.find('.id').text());
        var data = {
            id: id,
            codigo: id,
            nombre: $.trim($row.find('.nombre').text()),
        };
        var $ctx = ptrUsoarticuloContext && ptrUsoarticuloContext.length
            ? ptrUsoarticuloContext
            : $(this).closest('.tm-usoarticulo-campo');
        aplicarUsoarticuloEnContexto($ctx, data, { avanzar: false });
        $('#consultausoarticuloModal').modal('hide');
    });

    $(document).off('input.usoarticuloCodigo', '.codigousoarticulo').on('input.usoarticuloCodigo', '.codigousoarticulo', function () {
        $(this).removeAttr('data-usoarticulo-invalido');
    });

    $(document).off('blur.usoarticuloCodigo', '.codigousoarticulo').on('blur.usoarticuloCodigo', '.codigousoarticulo', function () {
        var $input = $(this);
        if ($input.data('consulta-abriendo') || modalConsultaUsoarticuloAbierto()) {
            $input.removeData('consulta-abriendo');
            return;
        }
        resolverPorCodigoUsoarticulo($input.val(), $input.closest('.tm-usoarticulo-campo'), { silencioso: true });
    });

    $(document).off('keydown.usoarticuloCodigo', '.codigousoarticulo').on('keydown.usoarticuloCodigo', '.codigousoarticulo', function (e) {
        var $input = $(this);
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            if (modalConsultaUsoarticuloAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaUsoarticuloDesdeInput($input);
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoUsoarticulo($input.val(), $input.closest('.tm-usoarticulo-campo'), {
            silencioso: false,
            avanzar: true,
        });
    });
}

$(function () {
    if ($('#consultausoarticuloModal').length || $('.consultausoarticulo').length || $('.tm-usoarticulo-campo').length) {
        activa_eventos_consultausoarticulo();
    }
});
