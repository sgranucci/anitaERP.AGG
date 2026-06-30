var ptrListaprecioContext;

function parsearHtmlConsultaListaprecio(respuesta) {
    var resp = String(respuesta || '').replace(/\\/g, '');
    try {
        var parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function actualizarLinkEditarListaprecio($ctx, listaprecioId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-listaprecio');
    if (!$link.length) {
        return;
    }
    var id = parseInt(listaprecioId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/stock/listaprecio/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarListaprecioEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.listaprecio_id').val(data.id);
        $ctx.find('.codigolistaprecio').val(data.codigo);
        $ctx.find('.nombrelistaprecio').val(data.nombre);
        actualizarLinkEditarListaprecio($ctx, data.id);
    }

    $('#listaprecio_id').val(data.id);
    $('#codigolistaprecio').val(data.codigo);
    $('#nombrelistaprecio').val(data.nombre);
    actualizarLinkEditarListaprecio($('.tm-listaprecio-campo').first(), data.id);
}

function limpiarListaprecioEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.listaprecio_id').val('');
        $ctx.find('.codigolistaprecio').val('');
        $ctx.find('.nombrelistaprecio').val('');
        actualizarLinkEditarListaprecio($ctx, 0);
    }

    $('#listaprecio_id').val('');
    $('#codigolistaprecio').val('');
    $('#nombrelistaprecio').val('');
    actualizarLinkEditarListaprecio($('.tm-listaprecio-campo').first(), 0);
}

function limpiarListaprecioManteniendoCodigo($ctx, codigo) {
    if ($ctx && $ctx.length) {
        $ctx.find('.listaprecio_id').val('');
        $ctx.find('.codigolistaprecio').val(codigo);
        $ctx.find('.nombrelistaprecio').val('');
        actualizarLinkEditarListaprecio($ctx, 0);
    }

    $('#listaprecio_id').val('');
    $('#codigolistaprecio').val(codigo);
    $('#nombrelistaprecio').val('');
    actualizarLinkEditarListaprecio($('.tm-listaprecio-campo').first(), 0);
}

function buscar_datos_listaprecio(consulta) {
    $.ajax({
        url: carpetaBase + '/stock/listaprecio/consultalistaprecio',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta || '',
        },
    })
        .done(function (respuesta) {
            $('#datoslistaprecio').html(parsearHtmlConsultaListaprecio(respuesta));
        })
        .fail(function () {
            $('#datoslistaprecio').html('<tr><td colspan="4">Error al consultar listas de precios</td></tr>');
        });
}

function resolverPorCodigoListaprecio(codigo, $ctx) {
    var cod = $.trim(codigo);
    if (cod === '') {
        limpiarListaprecioEnContexto($ctx);
        return;
    }

    var codOriginal = cod;
    var urlRes = carpetaBase + '/stock/leerlistaprecio/' + encodeURIComponent(cod);
    $.get(urlRes, function (data) {
        if (data && data.id) {
            aplicarListaprecioEnContexto($ctx, data);
        } else {
            limpiarListaprecioManteniendoCodigo($ctx, codOriginal);
            alert('Lista de precios no encontrada');
        }
    }).fail(function () {
        limpiarListaprecioManteniendoCodigo($ctx, codOriginal);
        alert('No se pudo validar la lista de precios');
    });
}

function esTeclaF1Listaprecio(e) {
    return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
}

function modalConsultaListaprecioAbierto() {
    var $m = $('#consultalistaprecioModal');
    return $m.length && $m.hasClass('show');
}

function abrirModalConsultaListaprecioDesdeInput($input) {
    ptrListaprecioContext = $input.closest('.tm-listaprecio-campo');
    $('#consultalistaprecioModal').modal('show');
    buscar_datos_listaprecio('');
}

function aceptarCodigoListaprecioDesdeInput($input) {
    var $ctx = $input.closest('.tm-listaprecio-campo');
    resolverPorCodigoListaprecio($input.val(), $ctx.length ? $ctx : null);
}

var listaprecioAtajosTecladoRegistrados = false;

function registrarAtajosTecladoListaprecio() {
    if (listaprecioAtajosTecladoRegistrados) {
        return;
    }
    listaprecioAtajosTecladoRegistrados = true;

    document.addEventListener('keydown', function (e) {
        var target = e.target;
        if (!target) {
            return;
        }

        var esCampoCodigo = target.classList.contains('codigolistaprecio') || target.id === 'codigolistaprecio';
        if (!esCampoCodigo) {
            return;
        }
        if (target.readOnly || target.disabled) {
            return;
        }
        if (!document.getElementById('form-general') || !document.getElementById('form-general').contains(target)) {
            return;
        }
        if (document.getElementById('form-general').getAttribute('data-consultas-modales-abm') === '1') {
            return;
        }

        if (esTeclaF1Listaprecio(e)) {
            if (modalConsultaListaprecioAbierto()) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            abrirModalConsultaListaprecioDesdeInput($(target));
            return;
        }

        if (e.which !== 13 && e.key !== 'Enter') {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        aceptarCodigoListaprecioDesdeInput($(target));
    }, true);
}

$(document).on('keyup', '#consultalistaprecio', function () {
    buscar_datos_listaprecio($(this).val());
});

function activa_eventos_consultalistaprecio() {
    registrarAtajosTecladoListaprecio();

    $('.consultalistaprecio').off('click.listaprecio').on('click.listaprecio', function () {
        ptrListaprecioContext = $(this).closest('.tm-listaprecio-campo');
        $('#consultalistaprecioModal').modal('show');
        buscar_datos_listaprecio('');
    });

    $('#consultalistaprecioModal').off('shown.bs.modal.listaprecio').on('shown.bs.modal.listaprecio', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultalistaprecioModal').off('click.listaprecio').on('click.listaprecio', function () {
        $('#consultalistaprecioModal').modal('hide');
    });

    $(document).off('click.eligeconsultalistaprecio').on('click.eligeconsultalistaprecio', '.eligeconsultalistaprecio', function () {
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            nombre: $.trim($row.find('.nombre').text()),
            codigo: $.trim($row.find('.codigo').text()),
        };

        if (ptrListaprecioContext && ptrListaprecioContext.length) {
            aplicarListaprecioEnContexto(ptrListaprecioContext, data);
        } else {
            aplicarListaprecioEnContexto($('.tm-listaprecio-campo').first(), data);
        }

        $('#consultalistaprecioModal').modal('hide');
    });

    $('.codigolistaprecio').off('change.listaprecio blur.listaprecio').on('change.listaprecio blur.listaprecio', function () {
        var $ctx = $(this).closest('.tm-listaprecio-campo');
        resolverPorCodigoListaprecio($(this).val(), $ctx.length ? $ctx : null);
    });
}
