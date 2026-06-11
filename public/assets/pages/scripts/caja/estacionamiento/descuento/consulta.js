var ptrDescuento_id;
var ptrCodigoDescuento_id;
var ptrNombreDescuento;

function clienteDescuentoFallbackEstacionamiento() {
    var est = window.ESTACIONAMIENTO || {};
    return est.clienteDescuento || null;
}

function codigoClienteDescuentoEstacionamiento() {
    var est = window.ESTACIONAMIENTO || {};
    return String(est.clienteDescuentoCodigo || '501').trim();
}

function predefinirClienteInternoDescuentoEstacionamiento() {
    var cliActual = ($('#cliente_descuento_id').val() || '').trim();
    if (cliActual) {
        return $.Deferred().resolve(null).promise();
    }
    var fb = clienteDescuentoFallbackEstacionamiento();
    if (fb && fb.id) {
        aplicarClienteInternoDescuentoEnPantalla(fb);
        return $.Deferred().resolve(fb).promise();
    }
    var cod = codigoClienteDescuentoEstacionamiento();
    if (!cod) {
        return $.Deferred().resolve(null).promise();
    }
    var base = typeof carpetaBase !== 'undefined' ? carpetaBase : '';
    return $.get(base + '/ventas/leerunclienteporcodigo/' + encodeURIComponent(cod)).then(
        function (data) {
            if (data && data.id && String(data.estado) === '0') {
                aplicarClienteInternoDescuentoEnPantalla(data);
                var est = window.ESTACIONAMIENTO || {};
                est.clienteDescuento = {
                    id: data.id,
                    codigo: data.codigo,
                    nombre: data.nombre || '',
                };
                return data;
            }
            return null;
        },
        function () {
            return null;
        }
    );
}

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
    if (typeof window.estOnClienteInternoDescuentoElegido === 'function') {
        window.estOnClienteInternoDescuentoElegido(cli || null);
    }
}

function aplicarClienteInternoDescuentoConFallback(data) {
    if (data && data.cliente && data.cliente.id) {
        aplicarClienteInternoDescuentoEnPantalla(data.cliente);
        return $.Deferred().resolve(data.cliente).promise();
    }
    return predefinirClienteInternoDescuentoEstacionamiento();
}

function pintarDescuentoEnPantalla(data) {
    if (!data || !data.id) {
        $('#descuento_estacionamiento_id').val('');
        $('#nombredescuento').val('');
        $('#codigodescuento').val('');
        aplicarClienteInternoDescuentoEnPantalla(null);
        mostrarPanelClienteInternoDescuento(false);
        return;
    }
    $('#descuento_estacionamiento_id').val(data.id);
    $('#nombredescuento').val(data.nombre || '');
    $('#codigodescuento').val(data.codigo != null ? String(data.codigo) : '');
    mostrarPanelClienteInternoDescuento(true);
    var cliActual = ($('#cliente_descuento_id').val() || '').trim();
    if (!cliActual) {
        void aplicarClienteInternoDescuentoConFallback(data);
    }
}

function buscar_datos_descuento(consulta) {
    $.ajax({
        url: carpetaBase + '/caja/estacionamiento/descuento/consultadescuento',
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
            console.log('error consulta descuento estacionamiento');
        });
}

function activa_eventos_consultadescuento() {
    $('.consultadescuento').off('click.estDescuento').on('click.estDescuento', function () {
        ptrDescuento_id = $(this).parents('tr').find('.descuento_estacionamiento_id');
        if (!ptrDescuento_id || !ptrDescuento_id.length) {
            ptrDescuento_id = $('#descuento_estacionamiento_id');
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

    $('#consultadescuentoModal').off('shown.bs.modal.estDescuento').on('shown.bs.modal.estDescuento', function () {
        $(this).find('[autofocus]').focus();
        buscar_datos_descuento($('#consultadescuento').val());
    });

    $('#aceptaconsultadescuentoModal').off('click.estDescuento').on('click.estDescuento', function () {
        $('#consultadescuentoModal').modal('hide');
    });

    $(document).off('click.estDescuentoElige', '.eligeconsultadescuento');
    $(document).on('click.estDescuentoElige', '.eligeconsultadescuento', function () {
        var seleccion = $(this).parents('tr').find('.id').html();
        var nombre = $(this).parents('tr').find('.nombre').html();
        var codigo = $(this).parents('tr').find('.codigo').html();

        if (ptrDescuento_id && ptrDescuento_id.length) {
            ptrDescuento_id.val(seleccion);
        }
        if (ptrCodigoDescuento_id && ptrCodigoDescuento_id.length) {
            ptrCodigoDescuento_id.val(codigo);
        }
        if (ptrNombreDescuento && ptrNombreDescuento.length) {
            ptrNombreDescuento.val(nombre);
        }

        $('#descuento_estacionamiento_id').val(seleccion);
        $('#nombredescuento').val(nombre);
        $('#codigodescuento').val(codigo);

        leerDescuentoPorCodigo(codigo, null, true);
        $('#consultadescuentoModal').modal('hide');
    });

    $('#codigodescuento').off('change.estDescuento').on('change.estDescuento', function (event) {
        event.preventDefault();
        leerDescuentoPorCodigo($('#codigodescuento').val(), null);
    });

    $(document).off('keyup.estDescuentoBuscar', '#consultadescuento');
    $(document).on('keyup.estDescuentoBuscar', '#consultadescuento', function () {
        buscar_datos_descuento($(this).val());
    });
}

function leerDescuentoPorCodigo(codigo, ptrrenglon, silencioso) {
    var cod = (codigo || '').trim();
    if (!cod) {
        pintarDescuentoEnPantalla(null);
        return $.Deferred().resolve(null).promise();
    }

    var urlRes = carpetaBase + '/caja/estacionamiento/descuento/leer/' + encodeURIComponent(cod);

    if (ptrrenglon) {
        $(ptrrenglon).parents('tr').find('.descuento_estacionamiento_id').val('');
        $(ptrrenglon).parents('tr').find('.codigodescuento').val('');
        $(ptrrenglon).parents('tr').find('.nombredescuento').val('');
    } else {
        $('#descuento_estacionamiento_id').val('');
        $('#nombredescuento').val('');
    }
    mostrarPanelClienteInternoDescuento(false);
    aplicarClienteInternoDescuentoEnPantalla(null);

    return $.get(urlRes).then(
        function (data) {
            if (!data || !data.id) {
                var err = new Error('Descuento no encontrado');
                if (!silencioso) {
                    pintarDescuentoEnPantalla(null);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(err.message);
                    }
                }
                return $.Deferred().reject(err).promise();
            }
            if (ptrrenglon) {
                $(ptrrenglon).parents('tr').find('.descuento_estacionamiento_id').val(data.id);
                $(ptrrenglon).parents('tr').find('.codigodescuento').val(data.codigo);
                $(ptrrenglon).parents('tr').find('.nombredescuento').val(data.nombre);
            }
            pintarDescuentoEnPantalla(data);
            if (typeof window.estOnDescuentoCargado === 'function') {
                window.estOnDescuentoCargado(data);
            }
            return data;
        },
        function (xhr) {
            var msg = 'Descuento no encontrado';
            try {
                msg = JSON.parse(xhr.responseText).error || msg;
            } catch (e) {
                /* noop */
            }
            if (!silencioso) {
                pintarDescuentoEnPantalla(null);
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                }
            }
            return $.Deferred().reject(new Error(msg)).promise();
        }
    );
}
