var ptrCategoriaContext;

function csrfTokenCategoria() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaCategoria(respuesta) {
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

function actualizarLinkEditarCategoria($ctx, categoriaId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-categoria');
    if (!$link.length) {
        return;
    }
    var id = parseInt(categoriaId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/categoria/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarCategoriaEnContexto($ctx, data, opciones) {
    var opts = opciones || {};
    var id = data && data.id != null ? data.id : '';
    var codigo = data && data.codigo != null ? data.codigo : '';
    var nombre = data && data.nombre != null ? data.nombre : '';
    if ($ctx && $ctx.length) {
        $ctx.find('.categoria_id').val(id);
        $ctx.find('.codigocategoria').val(codigo).removeAttr('data-categoria-invalido');
        $ctx.find('.nombrecategoria').val(nombre);
        actualizarLinkEditarCategoria($ctx, id);
    }
    if (opts.avanzar && id) {
        var sig = $ctx && $ctx.length ? $ctx.find('.codigocategoria').attr('data-siguiente') : '';
        if (sig && $(sig).length) {
            $(sig).trigger('focus');
        }
    }
    if (typeof opts.onDone === 'function') {
        opts.onDone(data);
    }
}

function limpiarCategoriaEnContexto($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.categoria_id').val('');
    $ctx.find('.codigocategoria').val('').removeAttr('data-categoria-invalido');
    $ctx.find('.nombrecategoria').val('');
    actualizarLinkEditarCategoria($ctx, 0);
}

function modalConsultaCategoriaAbierto() {
    var $m = $('#consultacategoriaModal');
    return $m.length && $m.hasClass('show');
}

function buscar_datos_categoria(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/categoria/consultacategoria',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenCategoria() },
        data: { consulta: consulta || '', _token: csrfTokenCategoria() },
    })
        .done(function (respuesta) {
            $('#datoscategoria').html(parsearHtmlConsultaCategoria(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar categorías';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar categorías';
            } else if (xhr && xhr.status === 419) {
                msg = 'Sesión expirada (CSRF). Recargue la página.';
            }
            $('#datoscategoria').html('<tr><td colspan="4">' + msg + '</td></tr>');
        });
}

function resolverPorCodigoCategoria(codigo, $ctx, opciones) {
    var opts = opciones || {};
    var cod = $.trim(codigo);
    if (!$ctx || !$ctx.length) {
        return;
    }
    if (cod === '') {
        limpiarCategoriaEnContexto($ctx);
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
        return;
    }

    $.get(carpetaBase + '/stock/leercategoria/' + encodeURIComponent(cod), function (data) {
        if (data && data.id) {
            aplicarCategoriaEnContexto($ctx, data, opts);
            return;
        }
        var $codigo = $ctx.find('.codigocategoria');
        var yaAvisado = $codigo.attr('data-categoria-invalido') === cod;
        $ctx.find('.categoria_id').val('');
        $ctx.find('.nombrecategoria').val('');
        $codigo.val(cod).attr('data-categoria-invalido', cod);
        actualizarLinkEditarCategoria($ctx, 0);
        if (!opts.silencioso && !yaAvisado) {
            alert('Categoría no encontrada');
            $codigo.trigger('focus');
        }
        if (typeof opts.onDone === 'function') {
            opts.onDone(null);
        }
    }).fail(function () {
        if (!opts.silencioso) {
            alert('No se pudo validar la categoría');
        }
    });
}

function abrirModalConsultaCategoriaDesdeInput($input) {
    ptrCategoriaContext = $input.closest('.tm-categoria-campo');
    $('#consultacategoria').val('');
    $('#consultacategoriaModal').modal('show');
    buscar_datos_categoria('');
}

function elegirPrimeraCategoriaDelModal() {
    var $btn = $('#datoscategoria .eligeconsultacategoria').first();
    if ($btn.length) {
        $btn.trigger('click');
        return true;
    }
    return false;
}

function activa_eventos_consultacategoria() {
    var $modal = $('#consultacategoriaModal');
    if ($modal.length && $modal.parent()[0] !== document.body) {
        $modal.appendTo('body');
    }

    $(document).off('mousedown.categoriaLupa', '.consultacategoria').on('mousedown.categoriaLupa', '.consultacategoria', function () {
        $(this).closest('.tm-categoria-campo').find('.codigocategoria').data('consulta-abriendo', 1);
    });

    $(document).off('click.categoriaLupa', '.consultacategoria').on('click.categoriaLupa', '.consultacategoria', function (e) {
        e.preventDefault();
        e.stopPropagation();
        abrirModalConsultaCategoriaDesdeInput($(this));
    });

    $modal.off('show.bs.modal.categoria').on('show.bs.modal.categoria', function () {
        var otrosAbiertos = $('.modal.show, .modal.in').not(this).length;
        if (otrosAbiertos > 0) {
            var zHijo = 1060 + (10 * otrosAbiertos);
            $(this).css('z-index', zHijo);
            setTimeout(function () {
                $('.modal-backdrop').not('.modal-stack').last().css('z-index', zHijo - 1).addClass('modal-stack');
            }, 0);
        }
    });

    $modal.off('shown.bs.modal.categoria').on('shown.bs.modal.categoria', function () {
        $(document).off('focusin.modal');
        $('#consultacategoria').trigger('focus');
    });

    $modal.off('hidden.bs.modal.categoria').on('hidden.bs.modal.categoria', function () {
        $(this).css('z-index', '');
        if (document.querySelectorAll('.modal.show, .modal.in').length > 0) {
            $('body').addClass('modal-open');
        }
    });

    $(document).off('keyup.consultacategoria', '#consultacategoria').on('keyup.consultacategoria', '#consultacategoria', function (e) {
        if (e.which === 13 || e.key === 'Enter') {
            return;
        }
        buscar_datos_categoria($(this).val());
    });

    $(document).off('keydown.consultacategoriaEnter', '#consultacategoria').on('keydown.consultacategoriaEnter', '#consultacategoria', function (e) {
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!elegirPrimeraCategoriaDelModal()) {
            buscar_datos_categoria($(this).val());
        }
    });

    $(document).off('submit.consultacategoria', '#consultacategoriaModal form').on('submit.consultacategoria', '#consultacategoriaModal form', function (e) {
        e.preventDefault();
        elegirPrimeraCategoriaDelModal();
        return false;
    });

    $('#aceptaconsultacategoriaModal').off('click.categoria').on('click.categoria', function () {
        if (!elegirPrimeraCategoriaDelModal()) {
            $('#consultacategoriaModal').modal('hide');
        }
    });

    $(document).off('click.eligeconsultacategoria', '.eligeconsultacategoria').on('click.eligeconsultacategoria', '.eligeconsultacategoria', function (e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            codigo: $.trim($row.find('.codigo').text()),
            nombre: $.trim($row.find('.nombre').text()),
        };
        var $ctx = ptrCategoriaContext && ptrCategoriaContext.length ? ptrCategoriaContext : $(this).closest('.tm-categoria-campo');
        aplicarCategoriaEnContexto($ctx, data, { avanzar: false });
        $('#consultacategoriaModal').modal('hide');
    });

    $(document).off('input.categoriaCodigo', '.codigocategoria').on('input.categoriaCodigo', '.codigocategoria', function () {
        $(this).removeAttr('data-categoria-invalido');
    });

    $(document).off('blur.categoriaCodigo', '.codigocategoria').on('blur.categoriaCodigo', '.codigocategoria', function () {
        var $input = $(this);
        if ($input.data('consulta-abriendo') || modalConsultaCategoriaAbierto()) {
            $input.removeData('consulta-abriendo');
            return;
        }
        resolverPorCodigoCategoria($input.val(), $input.closest('.tm-categoria-campo'), { silencioso: true });
    });

    $(document).off('keydown.categoriaCodigo', '.codigocategoria').on('keydown.categoriaCodigo', '.codigocategoria', function (e) {
        var $input = $(this);
        if (e.key === 'F1' || e.code === 'F1' || e.keyCode === 112) {
            if (modalConsultaCategoriaAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaCategoriaDesdeInput($input);
            return;
        }
        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resolverPorCodigoCategoria($input.val(), $input.closest('.tm-categoria-campo'), {
            silencioso: false,
            avanzar: true,
        });
    });
}

$(function () {
    if ($('#consultacategoriaModal').length || $('.consultacategoria').length || $('.tm-categoria-campo').length) {
        activa_eventos_consultacategoria();
    }
});
