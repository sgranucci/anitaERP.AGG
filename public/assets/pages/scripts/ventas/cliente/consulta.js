var ptrcliente_id;
var ptrnombrecliente;

function buscar_datos_cliente(consulta) {

    $.ajax({
        url: '/anitaERP/public/ventas/consultacliente',
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
        $("#datoscliente").html("");
        $("#datoscliente").html(resp);
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
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_cliente(valor);
    } else {
        buscar_datos_cliente();
    }
});

function activa_eventos_consultacliente()
{
    $('.consultacliente').on('click', function (event) {
        let cliente_id = $("#cliente_id").val();

        ptrcliente_id = $(this).parents("tr").find(".cliente_id");
		ptrnombrecliente = $(this).parents("tr").find(".nombrecliente");

        // Abre modal de consulta
        $("#consultaclienteModal").modal('show');

        ($.isNumeric(cliente_id))
            buscar_datos_cliente();
    });

    $('#consultaclienteModal').on('shown.bs.modal', function () {
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

        $(ptrcliente_id).val(seleccion);
        $(ptrnombrecliente).val(nombre);

        leeUnCliente(seleccion, 0)

        $("#cliente_id").val(seleccion);
        $("#nombrecliente").val(nombre);
        $("#codigocliente").val(codigo);

        $('#consultaclienteModal').modal('hide');
        
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
        let url_res = '/anitaERP/public/ventas/leeruncliente/'+cliente_id;

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
                    $(ptrrenglon).parents("tr").find(".cliente_id").val(data.codigo);
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
            var url_res = '/anitaERP/public/ventas/leeruncliente/'+cliente_id;
        else
            var url_res = '/anitaERP/public/ventas/leerunclienteporcodigo/'+codigocliente;

        $.get(url_res, function(data){
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
                    $("#cliente_id").val(data.id);
                    $("#nombrecliente").val(data.nombre);
                    $("#domicilio").val(data.domicilio);
                    $("#codigopostal").val(data.codigopostal);
                    $("#nroinscripcion").val(data.nroinscripcion);
                    $("#telefono").val(data.telefono);
                    $("#email").val(data.email);
                    $("#localidad_id").val(data.localidad_id);

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

                    completaDatosCliente();
                }
            }
        });

        setTimeout(() => {
        }, 1000);
    } 
    else
        $("#nombrecliente").val("");
}




