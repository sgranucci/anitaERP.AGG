var ptrDistribuidor_id;
var ptrCodigoDistribuidor;
var ptrNombreDistribuidor;

function actualizarLinkEditarDistribuidor($ctx, distribuidorId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-distribuidor');
    if (!$link.length) {
        return;
    }
    var id = parseInt(distribuidorId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/ventas/distribuidor/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarDistribuidorEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.distribuidor_id').val(data.id);
        $ctx.find('.codigodistribuidor').val(data.codigo);
        $ctx.find('.nombredistribuidor').val(data.nombre);
        actualizarLinkEditarDistribuidor($ctx, data.id);
    }

    $('#distribuidor_id').val(data.id);
    $('#codigodistribuidor').val(data.codigo);
    $('#nombredistribuidor').val(data.nombre);
    actualizarLinkEditarDistribuidor($('.tm-distribuidor-campo').first(), data.id);
}

function limpiarDistribuidorEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.distribuidor_id').val('');
        $ctx.find('.codigodistribuidor').val('');
        $ctx.find('.nombredistribuidor').val('');
        actualizarLinkEditarDistribuidor($ctx, 0);
    }

    $('#distribuidor_id').val('');
    $('#codigodistribuidor').val('');
    $('#nombredistribuidor').val('');
    actualizarLinkEditarDistribuidor($('.tm-distribuidor-campo').first(), 0);
}

function buscar_datos_distribuidor(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/distribuidor/consultadistribuidor',
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
        const resp = respuesta.replace(/\\/g, '');
        $('#datosdistribuidor').html('');
        $('#datosdistribuidor').html(resp);
    })
    .fail(function() {
        console.log('error consulta distribuidor');
    });
}

$(document).on('keyup', '#consultadistribuidor', function () {
    var valor = $(this).val();
    if (valor != '') {
        buscar_datos_distribuidor(valor);
    } else {
        buscar_datos_distribuidor();
    }
});

function resolverPorCodigoDistribuidor(codigo, $ctx) {
    var urlRes = carpetaBase + '/ventas/leerdistribuidor/' + encodeURIComponent(codigo);
    $.get(urlRes, function(data) {
        if (data && data.id) {
            aplicarDistribuidorEnContexto($ctx, data);
        } else {
            limpiarDistribuidorEnContexto($ctx);
        }
    }).fail(function() {
        limpiarDistribuidorEnContexto($ctx);
    });
}

function activa_eventos_consultadistribuidor() {
    $('.consultadistribuidor').off('click').on('click', function () {
        var $ctx = $(this).closest('.tm-distribuidor-campo');
        ptrDistribuidor_id = $ctx.length ? $ctx.find('.distribuidor_id') : null;
        ptrCodigoDistribuidor = $ctx.length ? $ctx.find('.codigodistribuidor') : null;
        ptrNombreDistribuidor = $ctx.length ? $ctx.find('.nombredistribuidor') : null;

        $('#consultadistribuidorModal').modal('show');
        buscar_datos_distribuidor('');
    });

    $('#consultadistribuidorModal').off('shown.bs.modal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultadistribuidorModal').off('click').on('click', function () {
        $('#consultadistribuidorModal').modal('hide');
    });

    $(document).off('click.eligeconsultadistribuidor').on('click.eligeconsultadistribuidor', '.eligeconsultadistribuidor', function () {
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            nombre: $.trim($row.find('.nombre').text()),
            codigo: $.trim($row.find('.codigo').text()),
        };

        if (ptrDistribuidor_id && ptrDistribuidor_id.length) {
            ptrDistribuidor_id.val(data.id);
            ptrCodigoDistribuidor.val(data.codigo);
            ptrNombreDistribuidor.val(data.nombre);
            actualizarLinkEditarDistribuidor(ptrDistribuidor_id.closest('.tm-distribuidor-campo'), data.id);
        }

        aplicarDistribuidorEnContexto($('.tm-distribuidor-campo').first(), data);
        $('#consultadistribuidorModal').modal('hide');
    });

    $('.codigodistribuidor').off('change blur').on('change blur', function () {
        var codigo = $.trim($(this).val());
        var $ctx = $(this).closest('.tm-distribuidor-campo');
        if (codigo === '') {
            limpiarDistribuidorEnContexto($ctx.length ? $ctx : null);
            return;
        }
        resolverPorCodigoDistribuidor(codigo, $ctx.length ? $ctx : null);
    });
}
