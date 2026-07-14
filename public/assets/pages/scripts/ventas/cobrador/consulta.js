var ptrCobrador_id;
var ptrCodigoCobrador;
var ptrNombreCobrador;

function actualizarLinkEditarCobrador($ctx, cobradorId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-cobrador');
    if (!$link.length) {
        return;
    }
    var id = parseInt(cobradorId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/ventas/cobrador/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarCobradorEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.cobrador_id').val(data.id);
        $ctx.find('.codigocobrador').val(data.codigo);
        $ctx.find('.nombrecobrador').val(data.nombre);
        actualizarLinkEditarCobrador($ctx, data.id);
    }

    $('#cobrador_id').val(data.id);
    $('#codigocobrador').val(data.codigo);
    $('#nombrecobrador').val(data.nombre);
    actualizarLinkEditarCobrador($('.tm-cobrador-campo').first(), data.id);
}

function limpiarCobradorEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.cobrador_id').val('');
        $ctx.find('.codigocobrador').val('');
        $ctx.find('.nombrecobrador').val('');
        actualizarLinkEditarCobrador($ctx, 0);
    }

    $('#cobrador_id').val('');
    $('#codigocobrador').val('');
    $('#nombrecobrador').val('');
    actualizarLinkEditarCobrador($('.tm-cobrador-campo').first(), 0);
}

function buscar_datos_cobrador(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/cobrador/consultacobrador',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            consulta: consulta,
        },
    })
    .done(function(respuesta) {
        var html = '';
        try {
            html = JSON.parse(respuesta).data || '';
        } catch (e) {
            html = (respuesta || '').replace(/\\/g, '');
        }
        $('#datoscobrador').html(html);
    })
    .fail(function() {
        console.log('error consulta cobrador');
    });
}

$(document).on('keyup', '#consultacobrador', function () {
    var valor = $(this).val();
    if (valor != '') {
        buscar_datos_cobrador(valor);
    } else {
        buscar_datos_cobrador();
    }
});

function resolverPorCodigoCobrador(codigo, $ctx) {
    var urlRes = carpetaBase + '/ventas/leercobrador/' + encodeURIComponent(codigo);
    $.get(urlRes, function(data) {
        if (data && data.id) {
            aplicarCobradorEnContexto($ctx, data);
        } else {
            limpiarCobradorEnContexto($ctx);
        }
    }).fail(function() {
        limpiarCobradorEnContexto($ctx);
    });
}

function activa_eventos_consultacobrador() {
    $('.consultacobrador').off('click').on('click', function () {
        var $ctx = $(this).closest('.tm-cobrador-campo');
        ptrCobrador_id = $ctx.length ? $ctx.find('.cobrador_id') : null;
        ptrCodigoCobrador = $ctx.length ? $ctx.find('.codigocobrador') : null;
        ptrNombreCobrador = $ctx.length ? $ctx.find('.nombrecobrador') : null;

        $('#consultacobradorModal').modal('show');
        buscar_datos_cobrador('');
    });

    $('#consultacobradorModal').off('shown.bs.modal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultacobradorModal').off('click').on('click', function () {
        $('#consultacobradorModal').modal('hide');
    });

    $(document).off('click.eligeconsultacobrador').on('click.eligeconsultacobrador', '.eligeconsultacobrador', function () {
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            nombre: $.trim($row.find('.nombre').text()),
            codigo: $.trim($row.find('.codigo').text()),
        };

        if (ptrCobrador_id && ptrCobrador_id.length) {
            ptrCobrador_id.val(data.id);
            ptrCodigoCobrador.val(data.codigo);
            ptrNombreCobrador.val(data.nombre);
            actualizarLinkEditarCobrador(ptrCobrador_id.closest('.tm-cobrador-campo'), data.id);
        }

        aplicarCobradorEnContexto($('.tm-cobrador-campo').first(), data);
        $('#consultacobradorModal').modal('hide');
    });

    $('.codigocobrador').off('change blur').on('change blur', function () {
        var codigo = $.trim($(this).val());
        var $ctx = $(this).closest('.tm-cobrador-campo');
        if (codigo === '') {
            limpiarCobradorEnContexto($ctx.length ? $ctx : null);
            return;
        }
        resolverPorCodigoCobrador(codigo, $ctx.length ? $ctx : null);
    });
}
