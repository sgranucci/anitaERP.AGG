var ptrVendedor_id;
var ptrCodigoVendedor_id;
var ptrNombreVendedor;

function actualizarLinkEditarVendedor($ctx, vendedorId) {
    if (!$ctx || !$ctx.length) {
        return;
    }
    var $link = $ctx.find('.btn-link-editar-vendedor');
    if (!$link.length) {
        return;
    }
    var id = parseInt(vendedorId, 10) || 0;
    if (id > 0) {
        $link.attr('href', carpetaBase + '/ventas/vendedor/' + id + '/editar?origen=modal_consulta&vista=consulta').removeClass('d-none');
    } else {
        $link.attr('href', '#').addClass('d-none');
    }
}

function aplicarVendedorEnContexto($ctx, data) {
    if ($ctx && $ctx.length) {
        $ctx.find('.vendedor_id').val(data.id);
        $ctx.find('.codigovendedor').val(data.codigo);
        $ctx.find('.nombrevendedor').val(data.nombre);
        actualizarLinkEditarVendedor($ctx, data.id);
    }

    $('#vendedor_id').val(data.id);
    $('#codigovendedor').val(data.codigo);
    $('#nombrevendedor').val(data.nombre);
    actualizarLinkEditarVendedor($('.tm-vendedor-campo').first(), data.id);
}

function limpiarVendedorEnContexto($ctx) {
    if ($ctx && $ctx.length) {
        $ctx.find('.vendedor_id').val('');
        $ctx.find('.codigovendedor').val('');
        $ctx.find('.nombrevendedor').val('');
        actualizarLinkEditarVendedor($ctx, 0);
    }

    $('#vendedor_id').val('');
    $('#codigovendedor').val('');
    $('#nombrevendedor').val('');
    actualizarLinkEditarVendedor($('.tm-vendedor-campo').first(), 0);
}

function buscar_datos_vendedor(consulta) {
    $.ajax({
        url: carpetaBase+'/ventas/vendedor/consultavendedor',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta,
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datosvendedor").html("");
        $("#datosvendedor").html(resp);
    })
    .fail (function() {
        console.log("error");
    });
}

$(document).on('keyup', '#consultavendedor', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_vendedor(valor);
    } else {
        buscar_datos_vendedor();
    }
});

function resolverPorCodigoVendedor(codigo, $ctx) {
    var urlRes = carpetaBase + '/ventas/leervendedor/' + encodeURIComponent(codigo);
    $.get(urlRes, function(data) {
        if (data && data.id) {
            aplicarVendedorEnContexto($ctx, data);
        } else {
            limpiarVendedorEnContexto($ctx);
        }
    }).fail(function() {
        limpiarVendedorEnContexto($ctx);
    });
}

function activa_eventos_consultavendedor()
{
    $('.consultavendedor').off('click').on('click', function () {
        var $ctx = $(this).closest('.tm-vendedor-campo');
        ptrVendedor_id = $ctx.length ? $ctx.find('.vendedor_id') : null;
        ptrCodigoVendedor_id = $ctx.length ? $ctx.find('.codigovendedor') : null;
        ptrNombreVendedor = $ctx.length ? $ctx.find('.nombrevendedor') : null;

        $("#consultavendedorModal").modal('show');
        buscar_datos_vendedor('');
    });

    $('#consultavendedorModal').off('shown.bs.modal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    });

    $('#aceptaconsultavendedorModal').off('click').on('click', function () {
        $('#consultavendedorModal').modal('hide');
    });

    $(document).off('click.eligeconsultavendedor').on('click.eligeconsultavendedor', '.eligeconsultavendedor', function () {
        var $row = $(this).closest('tr');
        var data = {
            id: $.trim($row.find('.id').text()),
            nombre: $.trim($row.find('.nombre').text()),
            codigo: $.trim($row.find('.codigo').text()),
        };

        if (ptrVendedor_id && ptrVendedor_id.length) {
            ptrVendedor_id.val(data.id);
            ptrCodigoVendedor_id.val(data.codigo);
            ptrNombreVendedor.val(data.nombre);
            actualizarLinkEditarVendedor(ptrVendedor_id.closest('.tm-vendedor-campo'), data.id);
        }

        aplicarVendedorEnContexto($('.tm-vendedor-campo').first(), data);
        $('#consultavendedorModal').modal('hide');
    });

    $('.codigovendedor').off('change blur').on('change blur', function () {
        var codigo = $.trim($(this).val());
        var $ctx = $(this).closest('.tm-vendedor-campo');
        if (codigo === '') {
            limpiarVendedorEnContexto($ctx.length ? $ctx : null);
            return;
        }
        resolverPorCodigoVendedor(codigo, $ctx.length ? $ctx : null);
    });
}

function desactiva_eventos_consulta_vendedor()
{
    $('.consultavendedor').off('click');
    $('#aceptaconsultavendedorModal').off('click');
    $(document).off('click.eligeconsultavendedor');
    $('.codigovendedor').off('change blur');
}
