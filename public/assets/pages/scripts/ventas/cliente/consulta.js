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
    $('.consultacliente').on('click', function (event) {
        ptrcliente_id = $(this).parents("tr").find(".cliente_id");
		ptrnombrecliente = $(this).parents("tr").find(".nombrecliente");

        // Abre modal de consulta
        $("#consultaclienteModal").modal('show');
    });

    $('#consultaclienteModal').on('shown.bs.modal', function () {
        restaurarUltimaConsultaClienteEnModal();
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultaclienteModal').on('click', function () {
        $('#consultaclienteModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultacliente', function (event) {
        event.preventDefault();
        
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();
        let codigo = $(this).parents("tr").find(".codigo").html();

        if (ptrcliente_id && ptrcliente_id.length) {
            $(ptrcliente_id).val(seleccion);
        }
        if (ptrnombrecliente && ptrnombrecliente.length) {
            $(ptrnombrecliente).val(nombre);
        }

        if (ptrcliente_id && ptrcliente_id.attr('id') === 'cliente_descuento_id') {
            $('#codigocliente_descuento').val(codigo || '');
            if (typeof aplicarClienteInternoDescuentoEnPantalla === 'function') {
                aplicarClienteInternoDescuentoEnPantalla({
                    id: seleccion,
                    codigo: codigo,
                    nombre: nombre,
                });
            }
            $('#consultaclienteModal').modal('hide');
            return;
        }

        leeUnCliente(seleccion, 0)

        $("#cliente_id").val(seleccion);
        $("#nombrecliente").val(nombre);

        if ($('#codigocliente').length > 0) 
            $("#codigocliente").val(codigo);

        $('#consultaclienteModal').modal('hide');
        
        if ($('#codigotransporte').length > 0) 
            $("#codigotransporte").focus();
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
                    $(ptrrenglon).parents("tr").find(".nombrecliente").val(data.nombre);

                    $("#cliente_id").val(data.id);
                    $("#nombrecliente").val(data.nombre);
                }
            }
        });

        setTimeout(() => {
        }, 1000);

    });

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
                if (data.estado != '0')
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
                    $("#nombrecliente").val(data.nombre);
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

                    if (typeof completaDatosCliente === 'function')
                        completaDatosCliente();
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




