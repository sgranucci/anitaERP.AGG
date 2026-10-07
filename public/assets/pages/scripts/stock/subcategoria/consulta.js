var ptrSubcategoriaContext;

function csrfTokenSubcategoria() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaSubcategoria(respuesta) {
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

function actualizarLinkEditarSubcategoria($ctx, subcategoriaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-subcategoria');
    if (!$link.length) {
        return;
    }
    var id = parseInt(subcategoriaId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/subcategoria/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarSubcategoriaEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : '';
    var nombre = data && data.nombre != null ? data.nombre : '';
    if ($ctx && $ctx.length) {
        $ctx.find('.subcategoria_id').val(id);
        $ctx.find('.codigosubcategoria').val(codigo).removeAttr('data-subcategoria-invalido');
        $ctx.find('.nombressubcategoria').val(nombre);
        actualizarLinkEditarSubcategoria($ctx, id);
    }
    if (opts.avanzar && id) {
        var sig = $ctx && $ctx.length ? $ctx.find('.codigosubcategoria').attr('data-siguiente') : '';
        if (sig && $(sig).length) {
            $(sig).trigger('focus');
        }
    }
    if (typeof opts.onDone === 'function') {
        opts.onDone(data);
    }
}

function limpiarSubcategoriaEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.subcategoria_id').val('');
    $ctx.find('.codigosubcategoria').val('').removeAttr('data-subcategoria-invalido');
    $ctx.find('.nombressubcategoria').val('');
    actualizarLinkEditarSubcategoria($ctx, 0);
}

function modalConsultaSubcategoriaAbierto() {
    var $m = $('#consultasubcategoriaModal');
    return $m.length && $m.hasClass('show');
}

function categoriaIdFiltroSubcategoria() {
    var sel = $('#consultasubcategoriaModal').data('filtraCategoria') || '';
    if (!sel) {
        return '';
    }
    var id = parseInt($(sel).val(), 10) || 0;
    return id > 0 ? id : '';
}

function buscar_datos_subcategoria(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/subcategoria/consultasubcategoria',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenSubcategoria() },
        data: {
            consulta: consulta || '',
            categoria_id: categoriaIdFiltroSubcategoria(),
            _token: csrfTokenSubcategoria(),
        },
    })
        .done(function (respuesta) {
            $('#datossubcategoria').html(parsearHtmlConsultaSubcategoria(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar subcategorías';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar subcategorías';
            } else if (xhr && xhr.status === 419) {
                msg = 'Sesión expirada (CSRF). Recargue la página.';
            }
            $('#datossubcategoria').html('<tr><td colspan="4">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoSubcategoria(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (cod === '') {
        limpiarSubcategoriaEnContexto($ctx);
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
        return;
    }

    $.get(carpetaBase + '/stock/leersubcategoria/' + encodeURIComponent(cod), function (data) {
        if (data && data.id) {
            aplicarSubcategoriaEnContexto($ctx, data, opts);
            return;
        }
        var $codigo = $ctx.find('.codigosubcategoria');
        var yaAvisado = $codigo.attr('data-subcategoria-invalido') === cod;
        $ctx.find('.subcategoria_id').val('');
        $ctx.find('.nombressubcategoria').val('');
        $codigo.val(cod).attr('data-subcategoria-invalido', cod);
        actualizarLinkEditarSubcategoria($ctx, 0);
        if (!opts.silencioso && !yaAvisado) {
            alert('Subcategoría no encontrada');
            $codigo.trigger('focus');
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
    }).fail(function () {
        if (!opts.silencioso) {
            alert('No se pudo validar la subcategoría');
        }
    });
}

function abrirModalConsultaSubcategoriaDesdeInput($input) {
    ptrSubcategoriaContext = $input.closest('.tm-subcategoria-campo');
    var filtra = ptrSubcategoriaContext.attr('data-filtra-categoria') || '';
    $('#consultasubcategoriaModal').data('filtraCategoria', filtra);
    $('#consultasubcategoria').val('');
    $('#consultasubcategoriaModal').modal('show');
    buscar_datos_subcategoria('');
}

function elegirPrimeraSubcategoriaDelModal() {
    var $btn = $('#datossubcategoria .eligeconsultasubcategoria').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

function activa_eventos_consultasubcategoria() {
    var $modal = $('#consultasubcategoriaModal');
    if ($modal.length && $modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }

    $(document).off('mousedown.subcategoriaLupa', '.consultasubcategoria').on('mousedown.subcategoriaLupa', '.consultasubcategoria', function () {
        $(this).closest('.tm-subcategoria-campo').find('.codigosubcategoria').data('consulta-abriendo', 1);
    });

    $(document).off('click.subcategoriaLupa', '.consultasubcategoria').on('click.subcategoriaLupa', '.consultasubcategoria', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaSubcategoriaDesdeInput($(this));
    });

    $modal.off('show.bs.modal.subcategoria').on('show.bs.modal.subcategoria', function () {
        var otrosAbiertos = $('.modal.show, .modal.in').not(this).length;
        if (otrosAbiertos > 0) {
            var zHijo = 1060 + (10 * otrosAbiertos);
            $(this).css('z-index', zHijo);
            setTimeout(function () {
                $('.modal-backdrop').not('.modal-stack').last().css('z-index', zHijo - 1).addClass('modal-stack');
            }, 0);
        }
    });

    $modal.off('shown.bs.modal.subcategoria').on('shown.bs.modal.subcategoria', function () {
        $(document).off('focusin.modal');
        $('#consultasubcategoria').trigger('focus');
    });

    $modal.off('hidden.bs.modal.subcategoria').on('hidden.bs.modal.subcategoria', function () {
        $(this).css('z-index', '');
        if (document.querySelectorAll('.modal.show, .modal.in').length > 0) {
            $('body').addClass('modal-open');
        }
    });

    $(document).off('keyup.consultasubcategoria', '#consultasubcategoria').on('keyup.consultasubcategoria', '#consultasubcategoria', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        buscar_datos_subcategoria($(this).val());
    });

    $(document).off('keydown.consultasubcategoriaEnter', '#consultasubcategoria').on('keydown.consultasubcategoriaEnter', '#consultasubcategoria', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimeraSubcategoriaDelModal()) {
            buscar_datos_subcategoria($(this).val());
        }
    });

    $(document).off('submit.consultasubcategoria', '#consultasubcategoriaModal form').on('submit.consultasubcategoria', '#consultasubcategoriaModal form', function (e) {
        e.preventDefault();
        elegirPrimeraSubcategoriaDelModal();
        return false;
    });

    $('#aceptaconsultasubcategoriaModal').off('click.subcategoria').on('click.subcategoria', function () {
        if (!elegirPrimeraSubcategoriaDelModal()) {
            $('#consultasubcategoriaModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultasubcategoria', '.eligeconsultasubcategoria').on('click.eligeconsultasubcategoria', '.eligeconsultasubcategoria', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            codigo: $.trim($row.find('.codigo').text()),
            nombre: $.trim($row.find('.nombre').text()),
        };
        var $ctx = ptrSubcategoriaContext && ptrSubcategoriaContext.length ? ptrSubcategoriaContext : $(this).closest('.tm-subcategoria-campo');
        aplicarSubcategoriaEnContexto($ctx, data, { avanzar: false });
        $('#consultasubcategoriaModal').modal('hide');
    });

    $(document).off('input.subcategoriaCodigo', '.codigosubcategoria').on('input.subcategoriaCodigo', '.codigosubcategoria', function () {
        $(this).removeAttr('data-subcategoria-invalido');
    });

    $(document).off('blur.subcategoriaCodigo', '.codigosubcategoria').on('blur.subcategoriaCodigo', '.codigosubcategoria', function () {
        var $input = $(this);
        if ($input.data('consulta-abriendo') || modalConsultaSubcategoriaAbierto()) {
            $input.removeData('consulta-abriendo');
            return;
        }
        resolverPorCodigoSubcategoria($input.val(), $input.closest('.tm-subcategoria-campo'), { silencioso: true });
    });

    $(document).off('keydown.subcategoriaCodigo', '.codigosubcategoria').on('keydown.subcategoriaCodigo', '.codigosubcategoria', function (e) {
        var $input = $(this);
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            if (modalConsultaSubcategoriaAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaSubcategoriaDesdeInput($input);
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoSubcategoria($input.val(), $input.closest('.tm-subcategoria-campo'), {
            silencioso: false,
            avanzar: true,
        });
    });
}

$(function () {
    if ($('#consultasubcategoriaModal').length || $('.consultasubcategoria').length || $('.tm-subcategoria-campo').length) {
        activa_eventos_consultasubcategoria();
    }
});
