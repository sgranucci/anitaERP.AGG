var ptrMventaContext;

function csrfTokenMventa() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaMventa(respuesta) {
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

function actualizarLinkEditarMventa($ctx, mventaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-mventa');
    if (!$link.length) {
        return;
    }
    var id = parseInt(mventaId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/mventa/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarMventaEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : '';
    var nombre = data && data.nombre != null ? data.nombre : '';
    if ($ctx && $ctx.length) {
        $ctx.find('.mventa_id').val(id);
        $ctx.find('.codigomventa').val(codigo).removeAttr('data-mventa-invalido');
        $ctx.find('.nombremventa').val(nombre);
        actualizarLinkEditarMventa($ctx, id);
    }
    if (opts.avanzar && id) {
        var sig = $ctx && $ctx.length ? $ctx.find('.codigomventa').attr('data-siguiente') : '';
        if (sig && $(sig).length) {
            $(sig).trigger('focus');
        }
    }
    if (typeof opts.onDone === 'function') {
        opts.onDone(data);
    }
}

function limpiarMventaEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.mventa_id').val('');
    $ctx.find('.codigomventa').val('').removeAttr('data-mventa-invalido');
    $ctx.find('.nombremventa').val('');
    actualizarLinkEditarMventa($ctx, 0);
}

function modalConsultaMventaAbierto() {
    var $m = $('#consultamventaModal');
    return $m.length && $m.hasClass('show');
}

function buscar_datos_mventa(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/mventa/consultamventa',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenMventa() },
        data: { consulta: consulta || '', _token: csrfTokenMventa() },
    })
        .done(function (respuesta) {
            $('#datosmventa').html(parsearHtmlConsultaMventa(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar marcas';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar marcas';
            } else if (xhr && xhr.status === 419) {
                msg = 'Sesión expirada (CSRF). Recargue la página.';
            }
            $('#datosmventa').html('<tr><td colspan="4">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoMventa(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (cod === '') {
        limpiarMventaEnContexto($ctx);
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
        return;
    }

    $.get(carpetaBase + '/stock/leermventa/' + encodeURIComponent(cod), function (data) {
        if (data && data.id) {
            aplicarMventaEnContexto($ctx, data, opts);
            return;
        }
        var $codigo = $ctx.find('.codigomventa');
        var yaAvisado = $codigo.attr('data-mventa-invalido') === cod;
        $ctx.find('.mventa_id').val('');
        $ctx.find('.nombremventa').val('');
        $codigo.val(cod).attr('data-mventa-invalido', cod);
        actualizarLinkEditarMventa($ctx, 0);
        if (!opts.silencioso && !yaAvisado) {
            alert('Marca no encontrada');
            $codigo.trigger('focus');
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
    }).fail(function () {
        if (!opts.silencioso) {
            alert('No se pudo validar la marca');
        }
    });
}

function abrirModalConsultaMventaDesdeInput($input) {
    ptrMventaContext = $input.closest('.tm-mventa-campo');
    $('#consultamventa').val('');
    $('#consultamventaModal').modal('show');
    buscar_datos_mventa('');
}

function elegirPrimeraMventaDelModal() {
    var $btn = $('#datosmventa .eligeconsultamventa').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

function activa_eventos_consultamventa() {
    var $modal = $('#consultamventaModal');
    if ($modal.length && $modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }

    $(document).off('mousedown.mventaLupa', '.consultamventa').on('mousedown.mventaLupa', '.consultamventa', function () {
        $(this).closest('.tm-mventa-campo').find('.codigomventa').data('consulta-abriendo', 1);
    });

    $(document).off('click.mventaLupa', '.consultamventa').on('click.mventaLupa', '.consultamventa', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaMventaDesdeInput($(this));
    });

    $modal.off('show.bs.modal.mventa').on('show.bs.modal.mventa', function () {
        var otrosAbiertos = $('.modal.show, .modal.in').not(this).length;
        if (otrosAbiertos > 0) {
            var zHijo = 1060 + (10 * otrosAbiertos);
            $(this).css('z-index', zHijo);
            setTimeout(function () {
                $('.modal-backdrop').not('.modal-stack').last().css('z-index', zHijo - 1).addClass('modal-stack');
            }, 0);
        }
    });

    $modal.off('shown.bs.modal.mventa').on('shown.bs.modal.mventa', function () {
        $(document).off('focusin.modal');
        $('#consultamventa').trigger('focus');
    });

    $modal.off('hidden.bs.modal.mventa').on('hidden.bs.modal.mventa', function () {
        $(this).css('z-index', '');
        if (document.querySelectorAll('.modal.show, .modal.in').length > 0) {
            $('body').addClass('modal-open');
        }
    });

    $(document).off('keyup.consultamventa', '#consultamventa').on('keyup.consultamventa', '#consultamventa', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        buscar_datos_mventa($(this).val());
    });

    $(document).off('keydown.consultamventaEnter', '#consultamventa').on('keydown.consultamventaEnter', '#consultamventa', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimeraMventaDelModal()) {
            buscar_datos_mventa($(this).val());
        }
    });

    $(document).off('submit.consultamventa', '#consultamventaModal form').on('submit.consultamventa', '#consultamventaModal form', function (e) {
        e.preventDefault();
        elegirPrimeraMventaDelModal();
        return false;
    });

    $('#aceptaconsultamventaModal').off('click.mventa').on('click.mventa', function () {
        if (!elegirPrimeraMventaDelModal()) {
            $('#consultamventaModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultamventa', '.eligeconsultamventa').on('click.eligeconsultamventa', '.eligeconsultamventa', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            codigo: $.trim($row.find('.codigo').text()),
            nombre: $.trim($row.find('.nombre').text()),
        };
        var $ctx = ptrMventaContext && ptrMventaContext.length ? ptrMventaContext : $(this).closest('.tm-mventa-campo');
        aplicarMventaEnContexto($ctx, data, { avanzar: false });
        $('#consultamventaModal').modal('hide');
    });

    $(document).off('input.mventaCodigo', '.codigomventa').on('input.mventaCodigo', '.codigomventa', function () {
        $(this).removeAttr('data-mventa-invalido');
    });

    $(document).off('blur.mventaCodigo', '.codigomventa').on('blur.mventaCodigo', '.codigomventa', function () {
        var $input = $(this);
        if ($input.data('consulta-abriendo') || modalConsultaMventaAbierto()) {
            $input.removeData('consulta-abriendo');
            return;
        }
        resolverPorCodigoMventa($input.val(), $input.closest('.tm-mventa-campo'), { silencioso: true });
    });

    $(document).off('keydown.mventaCodigo', '.codigomventa').on('keydown.mventaCodigo', '.codigomventa', function (e) {
        var $input = $(this);
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            if (modalConsultaMventaAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaMventaDesdeInput($input);
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoMventa($input.val(), $input.closest('.tm-mventa-campo'), {
            silencioso: false,
            avanzar: true,
        });
    });
}

$(function () {
    if ($('#consultamventaModal').length || $('.consultamventa').length || $('.tm-mventa-campo').length) {
        activa_eventos_consultamventa();
    }
});
