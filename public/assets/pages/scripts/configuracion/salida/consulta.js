var ptrSalidaContext;
var salidaConsultaAbriendo = false;

function csrfTokenSalida() {
    return $('meta[name="csrf-token"]').attr('content')
        || $('input[name="_token"]').first().val()
        || '';
}

function parsearHtmlConsultaSalida(respuesta) {
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

function actualizarLinkEditarSalida($ctx, id) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-salida');
    if (!$link.length) {
        return;
    }
    var salidaId = parseInt(id, 10) || 0;
    if (salidaId > 0) {
        $link.attr('href', carpetaBase + '/configuracion/salida/' + salidaId + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarSalidaEnContexto($ctx, data) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var id = data && data.id ? data.id : '';
    var nombre = data && data.nombre != null ? data.nombre : '';
    var descripcion = data && data.descripcion != null ? data.descripcion : '';
    $ctx.find('.salida_id').val(id);
    $ctx.find('.nombresalida').val(nombre).attr('data-nombre-resuelto', nombre).removeAttr('data-salida-invalido');
    $ctx.find('.descripcionsalida').val(descripcion);
    actualizarLinkEditarSalida($ctx, id);
}

function limpiarSalidaEnContexto($ctx, mantenerNombre) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.salida_id').val('');
    if (!mantenerNombre) {
        $ctx.find('.nombresalida').val('');
    }
    $ctx.find('.nombresalida').attr('data-nombre-resuelto', '').removeAttr('data-salida-invalido');
    $ctx.find('.descripcionsalida').val('');
    actualizarLinkEditarSalida($ctx, 0);
}

function buscar_datos_salida(consulta) {
    $.ajax({
        url: carpetaBase + '/configuracion/consultasalida',
        type: 'POST',
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfTokenSalida() },
        data: {
            consulta: consulta || '',
            _token: csrfTokenSalida(),
        },
    })
        .done(function (respuesta) {
            $('#datossalida').html(parsearHtmlConsultaSalida(respuesta));
        })
        .fail(function (xhr) {
            var msg = 'Error al consultar impresoras';
            if (xhr && xhr.status === 403) {
                msg = 'Sin permiso para consultar impresoras';
            }
            $('#datossalida').html('<tr><td colspan="5">' + msg + '</td></tr>');
        });
}

function resolverSalidaPorNombre(nombre, $ctx) {
    var texto = $.trim(nombre || '');
    if (texto === '') {
        limpiarSalidaEnContexto($ctx, false);
        return;
    }
    $.ajax({
        url: carpetaBase + '/configuracion/resolversalida',
        type: 'GET',
        dataType: 'json',
        data: { nombre: texto },
    })
        .done(function (data) {
            if (data && data.id) {
                aplicarSalidaEnContexto($ctx, data);
            } else {
                marcarSalidaInvalida($ctx);
            }
        })
        .fail(function () {
            marcarSalidaInvalida($ctx);
        });
}

function marcarSalidaInvalida($ctx) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    $ctx.find('.salida_id').val('');
    $ctx.find('.descripcionsalida').val('');
    $ctx.find('.nombresalida').attr('data-salida-invalido', '1').attr('data-nombre-resuelto', '');
    actualizarLinkEditarSalida($ctx, 0);
}

function activa_eventos_consultasalida() {
    $(document)
        .off('mousedown.consultaSalida', '.consultasalida')
        .on('mousedown.consultaSalida', '.consultasalida', function () {
            salidaConsultaAbriendo = true;
        });

    $(document)
        .off('click.consultaSalida', '.consultasalida')
        .on('click.consultaSalida', '.consultasalida', function () {
            salidaConsultaAbriendo = true;
            ptrSalidaContext = $(this).closest('.tm-salida-campo');
            $('#consultasalidaModal').modal('show');
        });

    $('#consultasalidaModal')
        .off('shown.bs.modal.consultaSalida')
        .on('shown.bs.modal.consultaSalida', function () {
            salidaConsultaAbriendo = false;
            var $input = $('#consultasalida');
            setTimeout(function () { $input.trigger('focus').select(); }, 0);
            buscar_datos_salida($input.val());
        })
        .off('hidden.bs.modal.consultaSalida')
        .on('hidden.bs.modal.consultaSalida', function () {
            salidaConsultaAbriendo = false;
        });

    $(document)
        .off('keyup.consultaSalida', '#consultasalida')
        .on('keyup.consultaSalida', '#consultasalida', function (e) {
            if (e.which === 13 || e.key === 'Enter') {
                return;
            }
            buscar_datos_salida($(this).val());
        });

    $(document)
        .off('keydown.consultaSalida', '#consultasalida')
        .on('keydown.consultaSalida', '#consultasalida', function (e) {
            if (e.which !== 13 && e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var $btn = $('#datossalida .eligeconsultasalida').first();
            if ($btn.length) {
                $btn.trigger('click');
            }
        });

    $(document)
        .off('click.eligeSalida', '.eligeconsultasalida')
        .on('click.eligeSalida', '.eligeconsultasalida', function () {
            var $tr = $(this).closest('tr');
            aplicarSalidaEnContexto(ptrSalidaContext, {
                id: $tr.find('.id').text().trim(),
                nombre: $tr.find('.nombre').text().trim(),
                descripcion: $tr.find('.descripcion').text().trim(),
            });
            $('#consultasalidaModal').modal('hide');
        });

    $('#aceptaconsultasalidaModal')
        .off('click.consultaSalida')
        .on('click.consultaSalida', function () {
            var $btn = $('#datossalida .eligeconsultasalida').first();
            if ($btn.length) {
                $btn.trigger('click');
            } else {
                $('#consultasalidaModal').modal('hide');
            }
        });

    $(document)
        .off('keydown.nombreSalida', '.nombresalida')
        .on('keydown.nombreSalida', '.nombresalida', function (e) {
            var $input = $(this);
            if ($input.prop('readonly') || $input.prop('disabled')) {
                return;
            }
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                $input.closest('.tm-salida-campo').find('.consultasalida').first().trigger('click');
            }
        });

    $(document)
        .off('blur.nombreSalida', '.nombresalida')
        .on('blur.nombreSalida', '.nombresalida', function () {
            if (salidaConsultaAbriendo || $('#consultasalidaModal').hasClass('show')) {
                return;
            }
            var $input = $(this);
            if ($input.prop('readonly') || $input.prop('disabled')) {
                return;
            }
            var $ctx = $input.closest('.tm-salida-campo');
            var texto = $.trim($input.val() || '');
            var resuelto = $.trim(String($input.attr('data-nombre-resuelto') || ''));
            var idActual = parseInt(String($ctx.find('.salida_id').val() || '0'), 10);
            if (texto === '') {
                limpiarSalidaEnContexto($ctx, false);
                return;
            }
            if (idActual > 0 && texto === resuelto) {
                return;
            }
            resolverSalidaPorNombre(texto, $ctx);
        });

    $(document)
        .off('input.nombreSalida', '.nombresalida')
        .on('input.nombreSalida', '.nombresalida', function () {
            if ($(this).prop('readonly') || $(this).prop('disabled')) {
                return;
            }
            var $ctx = $(this).closest('.tm-salida-campo');
            $ctx.find('.salida_id').val('');
            $ctx.find('.descripcionsalida').val('');
            $(this).removeAttr('data-salida-invalido');
            actualizarLinkEditarSalida($ctx, 0);
        });
}

$(function () {
    if ($('.tm-salida-campo, .consultasalida').length) {
        activa_eventos_consultasalida();
    }
});
