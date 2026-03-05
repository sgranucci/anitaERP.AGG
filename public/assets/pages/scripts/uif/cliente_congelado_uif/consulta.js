var ptrcliente_congelado_uif_id;
var ptrnombrecliente_congelado_uif;

function buscar_datos_cliente_congelado_uif(consulta) {
    $.ajax({
        url: '/anitaERP/public/uif/consultacliente_congelado_uif',
        type: 'POST',
        dataType: 'HTML',
	    headers: {
        	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    	},
        data: {
            consulta: consulta
        },
    })
    .done (function(respuesta) {
		const resp = respuesta.replace(/\\/g, '');
        $("#datoscliente_congelado_uif").html("");
        $("#datoscliente_congelado_uif").html(resp);
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

$(document).on('keyup', '#consultacliente_congelado_uif', function () {
    var valor = $(this).val();
    if (valor != "") {
        buscar_datos_cliente_congelado_uif(valor);
    } else {
        buscar_datos_cliente_congelado_uif();
    }
});

function activa_eventos_consultacliente_congelado_uif()
{
    // Consulta de servicios
    $('.consultacliente_congelado_uif').on('click', function (event) {
        let cliente_congelado_uif_id = $("#cliente_congelado_uif_id").val();

        ptrcliente_congelado_uif_id = $(this).parents("tr").find(".cliente_congelado_uif_id");
		ptrnombrecliente_congelado_uif = $(this).parents("tr").find(".nombrecliente_congelado_uif");

        // Abre modal de consulta
        $("#consultacliente_congelado_uifModal").modal('show');

        // Si tiene algo en el filtro de categorias abre directo la consulta
        ($.isNumeric(cliente_congelado_uif_id))
            buscar_datos_cliente_congelado_uif();
    });

    $('#consultacliente_congelado_uifModal').on('shown.bs.modal', function () {
        $(this).find('[autofocus]').focus();
    })

    $('#aceptaconsultacliente_congelado_uifModal').on('click', function () {
        $('#consultacliente_congelado_uifModal').modal('hide');
    });

    $(document).on('click', '.eligeconsultacliente_congelado_uif', function () {
        let seleccion = $(this).parents("tr").children().html();
        let nombre = $(this).parents("tr").find(".nombre").html();

        $(ptrcliente_congelado_uif_id).val(seleccion);
        $(ptrnombrecliente_congelado_uif).val(nombre);

        $("#cliente_congelado_uif_id").val(seleccion);
        $("#nombrecliente_congelado_uif").val(nombre);

        $('#consultacliente_congelado_uifModal').modal('hide');
    });

    $('#cliente_congelado_uif_id').on('change', function (event) {
        event.preventDefault();
        let cliente_congelado_uif_id = $("#cliente_congelado_uif_id").val();

        if ($.isNumeric(cliente_congelado_uif_id))
        {
            let url_res = '/anitaERP/public/uif/leeruncliente_congelado_uif/'+cliente_congelado_uif_id;

            $("#cliente_congelado_uif_id").val("");
            $("#nombrecliente_congelado_uif").val("");
            
            $.get(url_res, function(data){
                if (data)
                {
                    $("#cliente_congelado_uif_id").val(data.id);
                    $("#nombrecliente_congelado_uif").val(data.nombre);
                }
            });

            setTimeout(() => {
            }, 1000);
        } 
        else
            $("#nombrecliente_congelado_uif").val("");
    });

    $('.cliente_congelado_uif_id').on('change', function (event) {
        event.preventDefault();
        var ptrrenglon = this;

        // Lee concepto gasto
        let cliente_congelado_uif_id = $(this).val();
        let url_res = '/anitaERP/public/uif/leerunacliente_congelado_uif/'+cliente_congelado_uif_id;

        $.get(url_res, function(data){
            if (data)
            {
                $(ptrrenglon).parents("tr").find(".cliente_congelado_uif_id").val(data.id);
			    $(ptrrenglon).parents("tr").find(".nombrecliente_congelado_uif").val(data.nombre);

                $("#cliente_congelado_uif_id").val(data.id);
                $("#nombrecliente_congelado_uif").val(data.nombre);
            }
        });

        setTimeout(() => {
        }, 1000);

    });

}




