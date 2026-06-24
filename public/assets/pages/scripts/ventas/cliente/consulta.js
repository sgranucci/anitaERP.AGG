var ptrcliente_id;
var ptrnombrecliente;
var ultimaConsultaClienteTermino = '';
var ultimoDatosClienteHtml = '';

function mensajeInicialConsultaCliente() {
    return '<tr><td colspan="7" class="text-muted">Ingrese al menos 2 caracteres para buscar (solo clientes activos).</td></tr>';
}

function parsearHtmlConsultaCliente(respuesta) {
    const resp = String(respuesta || '').replace(/\\/g, '');
    try {
        const parsed = JSON.parse(resp);
        return parsed.data || '';
    } catch (e) {
        return resp;
    }
}

function guardarUltimaConsultaCliente(termino, html) {
    ultimaConsultaClienteTermino = termino;
    ultimoDatosClienteHtml = html;
}

function restaurarUltimaConsultaClienteEnModal() {
    if (ultimoDatosClienteHtml) {
        $('#consultacliente').val(ultimaConsultaClienteTermino);
        $('#datoscliente').html(ultimoDatosClienteHtml);
        return;
    }
    $('#consultacliente').val('');
    $('#datoscliente').html(mensajeInicialConsultaCliente());
}

/** Gastronomía y otras pantallas sin fila tr: resuelve inputs destino desde el botón lupa. */
function resolverPtrClienteDesdeBoton($btn) {
    var $gastro = $btn.closest('.gastro-campo-consulta');
    if ($gastro.length) {
        return {
            $id: $gastro.find('#cliente_id, .cliente_id').first(),
            $nombre: $gastro.find('#nombrecliente, .nombrecliente').first(),
            $codigo: $gastro.find('#codigocliente, .codigocliente').first(),
        };
    }
    var $tr = $btn.closest('tr');
    if ($tr.length) {
        return {
            $id: $tr.find('.cliente_id').first(),
            $nombre: $tr.find('.nombrecliente').first(),
            $codigo: $tr.find('.codigocliente').first(),
        };
    }
    return {
        $id: $('#cliente_id'),
        $nombre: $('#nombrecliente'),
        $codigo: $('#codigocliente'),
    };
}

function leerFilaClienteConsulta($link) {
    var $tr = $link.closest('tr');
    return {
        id: $.trim($tr.find('td.id').first().text()),
        nombre: $.trim($tr.find('td.nombre').first().text()),
        codigo: $.trim($tr.find('td.codigo').first().text()),
    };
}

function nombreClienteDisplayConCodigo(codigo, nombre) {
    codigo = codigo != null ? String(codigo).trim() : '';
    nombre = nombre != null ? String(nombre).trim() : '';
    if ($('#codigopedido').length && codigo !== '' && nombre !== '') {
        return codigo + ' - ' + nombre;
    }
    return nombre || codigo;
}

function esConsultaClienteInternoDescuento() {
    if ($('#consultaclienteModal').data('gastroConsultaDestino') === 'interno') {
        return true;
    }
    return !!(ptrcliente_id && ptrcliente_id.length && ptrcliente_id.attr('id') === 'cliente_descuento_id');
}

function aplicarSeleccionClienteInternoDescuento(fila) {
    if (typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
        aplicarClienteInternoDescuentoEnPantalla({
            id: fila.id,
            codigo: fila.codigo,
            nombre: fila.nombre,
        });
        return;
    }
    $('#cliente_descuento_id').val(fila.id || '');
    $('#codigocliente_descuento').val(fila.codigo || '');
    $('#nombrecliente_descuento').val(fila.nombre || '');
}

function aplicarSeleccionClienteFactura(fila) {
    if (ptrcliente_id && ptrcliente_id.length) {
        ptrcliente_id.val(fila.id);
    }
    if (ptrnombrecliente && ptrnombrecliente.length) {
        ptrnombrecliente.val(nombreClienteDisplayConCodigo(fila.codigo, fila.nombre));
    }
   	if (ptrcliente_id && ptrcliente_id.length) {
        var $cod = ptrcliente_id.closest('.gastro-campo-consulta, tr, .form-group').find('#codigocliente, .codigocliente').first();
        if ($cod.length) {
            $cod.val(fila.codigo);
        }
    } else {
        $('#cliente_id').val(fila.id);
        $('#nombrecliente').val(nombreClienteDisplayConCodigo(fila.codigo, fila.nombre));
        if ($('#codigocliente').length) {
            $('#codigocliente').val(fila.codigo);
        }
    }
    if ($('#codigocliente').length && fila.codigo) {
        $('#codigocliente').val(fila.codigo);
    }
    if (typeof window.gastroOnClienteFacturaElegido === 'function') {
        window.gastroOnClienteFacturaElegido(fila);
    } else if ($.isNumeric(fila.id)) {
        leeUnCliente(fila.id, 0);
    }
}

function buscar_datos_cliente(consulta) {
    var termino = (consulta != null && consulta !== undefined) ? String(consulta).trim() : '';

    $.ajax({
        url: carpetaBase+'/ventas/consultacliente',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: termino,
        },
    })
    .done (function(respuesta) {
        const html = parsearHtmlConsultaCliente(respuesta);
        $("#datoscliente").html(html);
        if (termino !== '') {
            guardarUltimaConsultaCliente(termino, html);
        }
    })
    .fail (function() {
        console.log("error");
    });
}

// Si pulsamos tecla enter en un Input no envia formulario
$("input").keydown(function (e){
    // Capturamos qué tecla ha sido
    var keyCode= e.which;
    // Si la tecla es el Intro/Enter
    if (keyCode == 13){
      // Evitamos que se ejecute eventos
      e.preventDefault();
      // Devolvemos falso
      return false;
    }
  });

$(document).on('keyup', '#consultacliente', function () {
    var valor = String($(this).val() || '').trim();
    if (valor === '' && ultimoDatosClienteHtml) {
        $('#datoscliente').html(ultimoDatosClienteHtml);
        return;
    }
    buscar_datos_cliente(valor);
});

function activa_eventos_consultacliente()
{
    $('.consultacliente')
        .off('click.consultaClienteAbrir')
        .on('click.consultaClienteAbrir', function () {
            var ctx = resolverPtrClienteDesdeBoton($(this));
            ptrcliente_id = ctx.$id;
            ptrnombrecliente = ctx.$nombre;
            $('#consultaclienteModal').data('gastroConsultaDestino', 'factura');
            $('#consultaclienteModal').modal('show');
        });

    $('#consultaclienteModal')
        .off('shown.bs.modal.consultaCliente')
        .on('shown.bs.modal.consultaCliente', function () {
            restaurarUltimaConsultaClienteEnModal();
            $(this).find('[autofocus]').focus();
        });

    $('#aceptaconsultaclienteModal')
        .off('click.consultaCliente')
        .on('click.consultaCliente', function () {
            $('#consultaclienteModal').modal('hide');
        });

    $(document)
        .off('click.eligeConsultaCliente', '.eligeconsultacliente')
        .on('click.eligeConsultaCliente', '.eligeconsultacliente', function (event) {
            event.preventDefault();

            var fila = leerFilaClienteConsulta($(this));
            if (!fila.id) {
                return;
            }

            if (esConsultaClienteInternoDescuento()) {
                aplicarSeleccionClienteInternoDescuento(fila);
                $('#consultaclienteModal').modal('hide');
                return;
            }

            aplicarSeleccionClienteFactura(fila);
            $('#consultaclienteModal').modal('hide');

            if ($('#codigotransporte').length > 0) {
                $('#codigotransporte').focus();
            }
        });

    // Si cambia el filtro blanquea el modal
    $('#areadestino_id').on('change', function (event) {
        event.preventDefault();

        $("#datoscliente").html("");

    });

    $('#cliente_id').on('change', function (event) {
        event.preventDefault();

        let cliente_id = $("#cliente_id").val();

        if ($.isNumeric(cliente_id))
        {
            leeUnCliente(cliente_id, 0)
        } 
        else
            $("#nombrecliente").val("");
    });

    $('#codigocliente').on('change', function (event) {
        event.preventDefault();

        let codigocliente = $("#codigocliente").val();

        if ($.isNumeric(codigocliente))
        {
            leeUnCliente(0, codigocliente);

            $("#codigotransporte").focus();
        } 
        else
            $("#nombrecliente").val("");
    });

    $('.cliente_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;
        let areadestino_id = $("#areadestino_id").val();

        let cliente_id = $(this).val();
        let url_res = carpetaBase+'/ventas/leeruncliente/'+cliente_id;

        $(ptrrenglon).parents("tr").find(".cliente_id").val("");
        $(ptrrenglon).parents("tr").find(".codigocliente").val("");
		$(ptrrenglon).parents("tr").find(".nombrecliente").val("");        

        $("#cliente_id").val("");
        $("#nombrecliente").val("");        

        $.get(url_res, function(data){
            if (data)
            {
                if (data.areadestino_id != areadestino_id)
                    alert('No coincide area de destino');
                else
                {
                    $(ptrrenglon).parents("tr").find(".cliente_id").val(data.id);
                    $(ptrrenglon).parents("tr").find(".codigocliente").val(data.codigo);
                    $(ptrrenglon).parents("tr").find(".nombrecliente").val(nombreClienteDisplayConCodigo(data.codigo, data.nombre));

                    $("#cliente_id").val(data.id);
                    $("#nombrecliente").val(nombreClienteDisplayConCodigo(data.codigo, data.nombre));
                }
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}

function invocarDatosClienteTrasSeleccion(clienteId, dataInmediato) {
    if (dataInmediato && typeof window.aplicarVendedorDesdeCliente === 'function') {
        window.aplicarVendedorDesdeCliente(dataInmediato);
    } else if (dataInmediato && typeof window.aplicarVendedorPedidoDesdeCliente === 'function') {
        window.aplicarVendedorPedidoDesdeCliente(dataInmediato);
    }

    if (typeof completaDatosCliente === 'function') {
        completaDatosCliente();
        return;
    }

    if (!clienteId || typeof asignaDatosCliente !== 'function') {
        return;
    }

    if (typeof completarCliente_Entrega === 'function') {
        completarCliente_Entrega(clienteId);
    }
    asignaDatosCliente(clienteId, true);
    if (typeof muestraTipoSuspension === 'function') {
        setTimeout(function () {
            muestraTipoSuspension();
        }, 1500);
    }
}

function leeUnCliente(cliente_id, codigocliente)
{
    if ($.isNumeric(cliente_id))
    {
        if (cliente_id > 0)
            var url_res = carpetaBase+'/ventas/leeruncliente/'+cliente_id;
        else
            var url_res = carpetaBase+'/ventas/leerunclienteporcodigo/'+codigocliente;

        $.get(url_res).done(function(data){
            if (data)
            {
                if (String(data.estado) !== '0')
                {
                    alert('Cliente '+data.nombre+' no activo');
                    $('#codigocliente').val('');
                    $('#nombrecliente').val('');
                    $('#codigocliente').focus();
                }
                else
                {
                    $('#codigocliente').val(data.codigo);
                    $("#cliente_id").val(data.id);
                    $("#nombrecliente").val(nombreClienteDisplayConCodigo(data.codigo, data.nombre));

                    $("#domicilio").val(data.domicilio);
                    $("#codigopostal").val(data.codigopostal);
                    $("#nroinscripcion").val(data.numerodocumento);
                    $("#telefono").val(data.telefono);
                    $("#email").val(data.email);
                    $("#localidad_id").val(data.localidad_id);
                    $("#zonavta_id").val(data.zonavtas_id);

                    if (data.zonavtas != null)
                    {
                        $("#codigozonavta").val(data.zonavtas.codigo);
                        $("#nombrezonavta").val(data.zonavtas.nombre);
                    }

                    if (data.localidades != null)
                    {
                        $("#desc_localidad").val(data.localidad_id);

                        $("#localidad_id").empty();
                        $("#localidad_id").append('<option value=""></option>');
                        $("#localidad_id").append('<option value="'+data.localidad_id+'"selected>'+data.localidades['nombre']+'</option>');
                    }

                    $("#provincia_id").val(data.provincia_id);

                    if (data.provincias != null)
                        $("#desc_provincia").val(data.provincias['nombre']);

                    $("#pais_id").val(data.pais_id);

                    if (data.paises != null)
                        $("#desc_pais").val(data.paises['nombre']);

                    invocarDatosClienteTrasSeleccion(data.id, data);
                }
            }
            else
            {
                $('#codigocliente').val('');
                $('#nombrecliente').val('');
                $('#codigocliente').focus();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
                $('#codigocliente').val('');
                $('#nombrecliente').val('');
                $('#codigocliente').focus();
        })

        setTimeout(() => {
        }, 1000);
    } 
    else
        $("#nombrecliente").val("");
}




