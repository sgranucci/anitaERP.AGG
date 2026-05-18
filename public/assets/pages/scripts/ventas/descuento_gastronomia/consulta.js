var ptrDescuento_id;
var ptrCodigoDescuento_id;
var ptrNombreDescuento;

function mostrarPanelClienteInternoDescuento(visible) {
    var panel = document.getElementById('panel-cliente-descuento');
    if (panel) {
        panel.classList.toggle('d-none', !visible);
    }
}

function aplicarClienteInternoDescuentoEnPantalla(cli) {
    $('#cliente_descuento_id').val(cli && cli.id ? cli.id : '');
    $('#codigocliente_descuento').val(cli && cli.codigo != null ? String(cli.codigo) : '');
    $('#nombrecliente_descuento').val(cli ? cli.nombre || '' : '');
    if (typeof window.gastroOnClienteInternoDescuentoElegido === 'function') {
        window.gastroOnClienteInternoDescuentoElegido(cli || null);
    }
}

/** @deprecated use aplicarClienteInternoDescuentoEnPantalla */
function aplicarClienteDescuentoEnPantalla(data) {
    var cli = data && data.cliente ? data.cliente : data;
    aplicarClienteInternoDescuentoEnPantalla(cli && cli.id ? cli : null);
}

function pintarDescuentoEnPantalla(data) {
    if (!data || !data.id) {
        $('#descuento_gastronomia_id').val('');
        $('#nombredescuento').val('');
        $('#codigodescuento').val('');
        aplicarClienteInternoDescuentoEnPantalla(null);
        mostrarPanelClienteInternoDescuento(false);
        return;
    }
    $('#descuento_gastronomia_id').val(data.id);
    $('#nombredescuento').val(data.nombre || '');
    $('#codigodescuento').val(data.codigo != null ? String(data.codigo) : '');
    mostrarPanelClienteInternoDescuento(true);
    var cliActual = ($('#cliente_descuento_id').val() || '').trim();
    if (!cliActual && data.cliente && data.cliente.id) {
        aplicarClienteInternoDescuentoEnPantalla(data.cliente);
    }
}

function buscar_datos_descuento(consulta) {
    $.ajax({
        url: carpetaBase + '/ventas/descuento-gastronomia/consultadescuento',
        type: 'POST',
        dataType: 'HTML',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        data: {
            consulta: consulta || '',
        },
    })
        .done(function (respuesta) {
            var html = '';
            if (typeof respuesta === 'string') {
                try {
                    html = JSON.parse(respuesta).data || '';
                } catch (e) {
                    html = respuesta.replace(/\\/g, '');
                }
            } else if (respuesta && typeof respuesta.data === 'string') {
                html = respuesta.data;
            }
            $('#datosdescuento').html(html);
        })
        .fail(function () {
            console.log('error consulta descuento');
        });
}

$('input').keydown(function (e) {
    var keyCode = e.which;
    if (keyCode == 13) {
        e.preventDefault();
        return false;
    }
});

$(document).on('keyup', '#consultadescuento', function () {
    var valor = $(this).val();
    buscar_datos_descuento(valor);
});

function activa_eventos_consultadescuento() {
    $('.consultadescuento').on('click', function () {
        ptrDescuento_id = $(this).parents('tr').find('.descuento_gastronomia_id');
        if (!ptrDescuento_id || !ptrDescuento_id.length) {
            ptrDescuento_id = $('#descuento_gastronomia_id');
        }
        ptrCodigoDescuento_id = $(this).parents('tr').find('.codigodescuento');
        if (!ptrCodigoDescuento_id || !ptrCodigoDescuento_id.length) {
            ptrCodigoDescuento_id = $('#codigodescuento');
        }
        ptrNombreDescuento = $(this).parents('tr').find('.nombredescuento');
        if (!ptrNombreDescuento || !ptrNombreDescuento.length) {
            ptrNombreDescuento = $('#nombredescuento');
        }

        $('#consultadescuentoModal').modal('show');
    });

    $('#consultadescuentoModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
        buscar_datos_descuento($('#consultadescuento').val());
    });

    $('#aceptaconsultadescuentoModal').on('click', function () {
        $('#consultadescuentoModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultadescuento', function () {
        var seleccion = $(this).parents('tr').find('.id').html();
        var nombre = $(this).parents('tr').find('.nombre').html();
        var codigo = $(this).parents('tr').find('.codigo').html();
        var clienteTxt = $(this).parents('tr').find('.cliente_descuento').html() || '';

        if (ptrDescuento_id && ptrDescuento_id.length) {
            ptrDescuento_id.val(seleccion);
        }
        if (ptrCodigoDescuento_id && ptrCodigoDescuento_id.length) {
            ptrCodigoDescuento_id.val(codigo);
        }
        if (ptrNombreDescuento && ptrNombreDescuento.length) {
            ptrNombreDescuento.val(nombre);
        }

        $('#descuento_gastronomia_id').val(seleccion);
        $('#nombredescuento').val(nombre);
        $('#codigodescuento').val(codigo);

        leerDescuentoPorCodigo(codigo, null, true);
        $('#consultadescuentoModal').modal('hide');
    });

    $('#codigodescuento').on('change', function (event) {
        event.preventDefault();
        leerDescuentoPorCodigo($('#codigodescuento').val(), null);
    });

    $('.codigodescuento').on('change', function (event) {
        event.preventDefault();
        leerDescuentoPorCodigo($(this).val(), $(this));
    });
}

function leerDescuentoPorCodigo(codigo, ptrrenglon, silencioso) {
    var cod = (codigo || '').trim();
    if (!cod) {
        pintarDescuentoEnPantalla(null);
        return;
    }

    var url_res = carpetaBase + '/ventas/descuento-gastronomia/leer/' + encodeURIComponent(cod);

    if (ptrrenglon) {
        $(ptrrenglon).parents('tr').find('.descuento_gastronomia_id').val('');
        $(ptrrenglon).parents('tr').find('.codigodescuento').val('');
        $(ptrrenglon).parents('tr').find('.nombredescuento').val('');
    } else {
        $('#descuento_gastronomia_id').val('');
        $('#nombredescuento').val('');
    }
    mostrarPanelClienteInternoDescuento(false);
    aplicarClienteInternoDescuentoEnPantalla(null);

    $.get(url_res, function (data) {
        if (!data || !data.id) {
            return;
        }
        if (ptrrenglon) {
            $(ptrrenglon).parents('tr').find('.descuento_gastronomia_id').val(data.id);
            $(ptrrenglon).parents('tr').find('.codigodescuento').val(data.codigo);
            $(ptrrenglon).parents('tr').find('.nombredescuento').val(data.nombre);
        }
        pintarDescuentoEnPantalla(data);
    }).fail(function () {
        if (!silencioso) {
            pintarDescuentoEnPantalla(null);
        }
    });
}
