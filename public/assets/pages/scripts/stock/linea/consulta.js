var ptrLineaContext;

function csrfTokenLinea() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaLinea(respuesta) {
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

function actualizarLinkEditarLinea($ctx, lineaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-linea');
    if (!$link.length) {
        return;
    }
    var id = parseInt(lineaId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/linea/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarLineaEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : '';
    var nombre = data && data.nombre != null ? data.nombre : '';
    if ($ctx && $ctx.length) {
        $ctx.find('.linea_id').val(id);
        $ctx.find('.codigolinea').val(codigo).removeAttr('data-linea-invalido');
        $ctx.find('.nombrelinea').val(nombre);
        actualizarLinkEditarLinea($ctx, id);
    }
    if (opts.avanzar && id) {
        var sig = $ctx && $ctx.length ? $ctx.find('.codigolinea').attr('data-siguiente') : '';
        if (sig && $(sig).length) {
            $(sig).trigger('focus');
        }
    }
    if (typeof opts.onDone === 'function') {
        opts.onDone(data);
    }
}

function limpiarLineaEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.linea_id').val('');
    $ctx.find('.codigolinea').val('').removeAttr('data-linea-invalido');
    $ctx.find('.nombrelinea').val('');
    actualizarLinkEditarLinea($ctx, 0);
}

function modalConsultaLineaAbierto() {
    var $m = $('#consultalineaModal');
    return $m.length && $m.hasClass('show');
}

function buscar_datos_linea(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/linea/consultalinea',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenLinea() },
        data: { consulta: consulta || '', _token: csrfTokenLinea() },
    })
        .done(function (respuesta) {
            $('#datoslinea').html(parsearHtmlConsultaLinea(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar líneas';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar líneas';
            } else if (xhr && xhr.status === 419) {
                msg = 'Sesión expirada (CSRF). Recargue la página.';
            }
            $('#datoslinea').html('<tr><td colspan="4">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoLinea(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (cod === '') {
        limpiarLineaEnContexto($ctx);
        if (opts.avanzar) {
            var sigVacio = $ctx.find('.codigolinea').attr('data-siguiente');
            if (sigVacio && $(sigVacio).length) {
                $(sigVacio).trigger('focus');
            }
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
        return;
    }

    $.get(carpetaBase + '/stock/leerlinea/' + encodeURIComponent(cod), function (data) {
        if (data && data.id) {
            aplicarLineaEnContexto($ctx, data, opts);
            return;
        }
        var $codigo = $ctx.find('.codigolinea');
        var yaAvisado = $codigo.attr('data-linea-invalido') === cod;
        $ctx.find('.linea_id').val('');
        $ctx.find('.nombrelinea').val('');
        $codigo.val(cod).attr('data-linea-invalido', cod);
        actualizarLinkEditarLinea($ctx, 0);
        if (!opts.silencioso && !yaAvisado) {
            alert('Línea no encontrada');
            $codigo.trigger('focus');
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
    }).fail(function () {
        if (!opts.silencioso) {
            alert('No se pudo validar la línea');
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
    });
}

function abrirModalConsultaLineaDesdeInput($input) {
    ptrLineaContext = $input.closest('.tm-linea-campo');
    $('#consultalinea').val('');
    $('#consultalineaModal').modal('show');
    buscar_datos_linea('');
}

function elegirPrimeraLineaDelModal() {
    var $btn = $('#datoslinea .eligeconsultalinea').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

function activa_eventos_consultalinea() {
    var $modal = $('#consultalineaModal');
    if ($modal.length && $modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }

    $(document).off('mousedown.lineaLupa', '.consultalinea').on('mousedown.lineaLupa', '.consultalinea', function () {
        $(this).closest('.tm-linea-campo').find('.codigolinea').data('consulta-abriendo', 1);
    });

    $(document).off('click.lineaLupa', '.consultalinea').on('click.lineaLupa', '.consultalinea', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaLineaDesdeInput($(this));
    });

    $modal.off('show.bs.modal.linea').on('show.bs.modal.linea', function () {
        var otrosAbiertos = $('.modal.show, .modal.in').not(this).length;
        if (otrosAbiertos > 0) {
            var zHijo = 1060 + (10 * otrosAbiertos);
            $(this).css('z-index', zHijo);
            setTimeout(function () {
                $('.modal-backdrop').not('.modal-stack').last().css('z-index', zHijo - 1).addClass('modal-stack');
            }, 0);
        }
    });

    $modal.off('shown.bs.modal.linea').on('shown.bs.modal.linea', function () {
        $(document).off('focusin.modal');
        $('#consultalinea').trigger('focus');
    });

    $modal.off('hidden.bs.modal.linea').on('hidden.bs.modal.linea', function () {
        $(this).css('z-index', '');
        if (document.querySelectorAll('.modal.show, .modal.in').length > 0) {
            $('body').addClass('modal-open');
        }
    });

    $(document).off('keyup.consultalinea', '#consultalinea').on('keyup.consultalinea', '#consultalinea', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        buscar_datos_linea($(this).val());
    });

    $(document).off('keydown.consultalineaEnter', '#consultalinea').on('keydown.consultalineaEnter', '#consultalinea', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimeraLineaDelModal()) {
            buscar_datos_linea($(this).val());
        }
    });

    $(document).off('submit.consultalinea', '#consultalineaModal form').on('submit.consultalinea', '#consultalineaModal form', function (e) {
        e.preventDefault();
        elegirPrimeraLineaDelModal();
        return false;
    });

    $('#aceptaconsultalineaModal').off('click.linea').on('click.linea', function () {
        if (!elegirPrimeraLineaDelModal()) {
            $('#consultalineaModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultalinea', '.eligeconsultalinea').on('click.eligeconsultalinea', '.eligeconsultalinea', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            codigo: $.trim($row.find('.codigo').text()),
            nombre: $.trim($row.find('.nombre').text()),
        };
        var $ctx = ptrLineaContext && ptrLineaContext.length ? ptrLineaContext : $(this).closest('.tm-linea-campo');
        aplicarLineaEnContexto($ctx, data, { avanzar: false });
        $('#consultalineaModal').modal('hide');
    });

    $(document).off('input.lineaCodigo', '.codigolinea').on('input.lineaCodigo', '.codigolinea', function () {
        $(this).removeAttr('data-linea-invalido');
    });

    $(document).off('blur.lineaCodigo', '.codigolinea').on('blur.lineaCodigo', '.codigolinea', function () {
        var $input = $(this);
        if ($input.data('consulta-abriendo') || modalConsultaLineaAbierto()) {
            $input.removeData('consulta-abriendo');
            return;
        }
        resolverPorCodigoLinea($input.val(), $input.closest('.tm-linea-campo'), { silencioso: true });
    });

    $(document).off('keydown.lineaCodigo', '.codigolinea').on('keydown.lineaCodigo', '.codigolinea', function (e) {
        var $input = $(this);
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            if (modalConsultaLineaAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaLineaDesdeInput($input);
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoLinea($input.val(), $input.closest('.tm-linea-campo'), {
            silencioso: false,
            avanzar: true,
        });
    });
}

$(function () {
    if ($('#consultalineaModal').length || $('.consultalinea').length || $('.tm-linea-campo').length) {
        activa_eventos_consultalinea();
    }
});
